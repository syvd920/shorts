# 부동산 쇼츠 제작기 Prototype V1

## 실행
1. Python 3.10+와 FFmpeg 설치
2. `pip install -r requirements.txt`
3. `python app.py`
4. 브라우저에서 `http://localhost:8000`

## 현재 동작
- 매물 직접 등록
- 다중 사진 업로드
- 무료 규칙 기반 쇼츠 대본 초안
- 브라우저 TTS 미리듣기
- 자동 자막 생성/번인
- 사진 줌 모션
- 상호/전화번호 하단 표시
- 1080x1920 MP4 렌더링

## 다음 개발 단계
- OpenAI/Gemini 등 텍스트 AI 연동
- ElevenLabs/OpenAI 등 TTS 음성 파일 생성 및 MP4 합성
- 자막 스타일 템플릿
- BGM, 로고, 엔딩카드
- 회원/요금제/사용량
- 선방 CP API 매물 연동

※ V1은 API 키 없이 핵심 제작 흐름과 렌더 품질을 확인하기 위한 프로토타입입니다.
