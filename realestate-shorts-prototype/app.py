from fastapi import FastAPI, UploadFile, File, Form
from fastapi.responses import FileResponse, HTMLResponse, JSONResponse
from fastapi.staticfiles import StaticFiles
from pathlib import Path
from typing import List
import subprocess, uuid, shutil, math, re, json

BASE=Path(__file__).parent
UPLOADS=BASE/'uploads'; OUTPUTS=BASE/'outputs'
UPLOADS.mkdir(exist_ok=True); OUTPUTS.mkdir(exist_ok=True)
app=FastAPI(title='부동산 쇼츠 제작기 Prototype')
app.mount('/static', StaticFiles(directory=BASE/'static'), name='static')

@app.get('/', response_class=HTMLResponse)
def home():
    return (BASE/'static'/'index.html').read_text(encoding='utf-8')

def build_script(data: dict):
    name=data.get('name') or '오늘의 추천 매물'
    deal=data.get('deal') or '매매'
    price=data.get('price') or '가격 상담'
    area=data.get('area') or ''
    rooms=data.get('rooms') or ''
    baths=data.get('baths') or ''
    direction=data.get('direction') or ''
    movein=data.get('movein') or ''
    features=[x.strip() for x in (data.get('features') or '').split(',') if x.strip()]
    tone=data.get('tone') or '전문적으로'
    intro = f"오늘 소개해드릴 매물은 {name}입니다."
    facts=[]
    if deal or price: facts.append(f"{deal} 기준 {price}입니다.")
    if area: facts.append(f"면적은 {area}입니다.")
    if rooms or baths: facts.append(f"방 {rooms or '-'}개, 욕실 {baths or '-'}개 구조입니다.")
    if direction: facts.append(f"방향은 {direction}입니다.")
    if movein: facts.append(f"입주는 {movein} 가능합니다.")
    if features: facts.append("이 매물의 핵심 포인트는 " + ', '.join(features[:4]) + "입니다.")
    close="사진을 보시면서 공간 구성을 확인해보세요. 자세한 조건과 방문 상담은 중개사무소로 문의해주세요."
    if tone=='빠르고 강하게': intro=f"조건 좋은 {name}, 핵심만 빠르게 보여드리겠습니다."
    elif tone=='고급스럽게': intro=f"오늘은 {name}의 공간과 조건을 차분하게 살펴보겠습니다."
    return ' '.join([intro]+facts+[close])

@app.post('/api/generate-script')
async def generate_script(payload: dict):
    return {'script': build_script(payload)}

def srt_time(sec):
    ms=int((sec-int(sec))*1000); s=int(sec)%60; m=(int(sec)//60)%60; h=int(sec)//3600
    return f"{h:02}:{m:02}:{s:02},{ms:03}"

def make_srt(script, total, path):
    chunks=[]
    # split on punctuation, then keep subtitle chunks compact
    sentences=[x.strip() for x in re.split(r'(?<=[.!?。])\s*', script) if x.strip()]
    for sent in sentences:
        if len(sent)<=20: chunks.append(sent); continue
        words=sent.split(); cur=''
        if len(words)>1:
            for w in words:
                if len(cur)+len(w)+1>20 and cur:
                    chunks.append(cur); cur=w
                else: cur=(cur+' '+w).strip()
            if cur: chunks.append(cur)
        else:
            for i in range(0,len(sent),18): chunks.append(sent[i:i+18])
    if not chunks: chunks=[script or '매물 소개']
    weights=[max(1,len(c)) for c in chunks]; sw=sum(weights); t=0
    lines=[]
    for i,(c,w) in enumerate(zip(chunks,weights),1):
        dur=total*w/sw; start=t; end=min(total,t+dur); t=end
        lines += [str(i), f"{srt_time(start)} --> {srt_time(end)}", c, '']
    path.write_text('\n'.join(lines),encoding='utf-8')

@app.post('/api/render')
async def render(script: str=Form(...), brand: str=Form('공인중개사사무소'), phone: str=Form(''), images: List[UploadFile]=File(...)):
    job=uuid.uuid4().hex[:10]; work=UPLOADS/job; work.mkdir(parents=True)
    files=[]
    for i,img in enumerate(images):
        ext=Path(img.filename or '.jpg').suffix.lower() or '.jpg'; p=work/f'{i:02}{ext}'
        with p.open('wb') as f: shutil.copyfileobj(img.file,f)
        files.append(p)
    if not files: return JSONResponse({'error':'사진이 필요합니다.'},400)
    # prototype timing: 3.2 sec/photo, min 18 sec, max 60 sec
    total=max(18,min(60,len(files)*3.2)); per=total/len(files)
    clips=[]
    for i,p in enumerate(files):
        out=work/f'clip_{i:02}.mp4'; clips.append(out)
        zoom = "zoom+0.0005" if i%2==0 else "if(lte(zoom,1.10),1.10,zoom-0.0004)"
        vf=(f"scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,"
            f"zoompan=z='{zoom}':d={int(per*30)}:s=1080x1920:fps=30,format=yuv420p")
        subprocess.run(['ffmpeg','-y','-loop','1','-i',str(p),'-vf',vf,'-t',f'{per:.2f}','-an','-c:v','libx264','-preset','veryfast',str(out)],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,check=True)
    concat=work/'concat.txt'; concat.write_text('\n'.join([f"file '{c.as_posix()}'" for c in clips]),encoding='utf-8')
    joined=work/'joined.mp4'
    subprocess.run(['ffmpeg','-y','-f','concat','-safe','0','-i',str(concat),'-c','copy',str(joined)],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,check=True)
    srt=work/'subs.srt'; make_srt(script,total,srt)
    out=OUTPUTS/f'shorts_{job}.mp4'
    font='Noto Sans CJK KR'
    footer=f"{brand}" + (f"  |  {phone}" if phone else '')
    # escape paths for subtitles filter
    srtp=str(srt).replace(':','\\:').replace("'","\\'")
    filt=(f"subtitles='{srtp}':force_style='FontName={font},FontSize=20,PrimaryColour=&H00FFFFFF,OutlineColour=&H80000000,BorderStyle=3,Outline=2,Shadow=0,Alignment=2,MarginV=180',"
          f"drawbox=x=0:y=h-115:w=w:h=115:color=black@0.62:t=fill,"
          f"drawtext=font='{font}':text='{footer}':fontcolor=white:fontsize=36:x=(w-text_w)/2:y=h-75")
    subprocess.run(['ffmpeg','-y','-i',str(joined),'-vf',filt,'-c:v','libx264','-preset','veryfast','-movflags','+faststart',str(out)],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,check=True)
    return {'url':f'/api/download/{out.name}','duration':round(total,1)}

@app.get('/api/download/{filename}')
def download(filename:str):
    p=OUTPUTS/Path(filename).name
    if not p.exists(): return JSONResponse({'error':'not found'},404)
    return FileResponse(p,media_type='video/mp4',filename=p.name)

if __name__=='__main__':
    import uvicorn; uvicorn.run(app,host='0.0.0.0',port=8000)
