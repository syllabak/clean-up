#!/usr/bin/env bash
# Mixe la musique de fond (fournie), la voix off et la vidéo muette -> out/cleanup-parcours-client.mp4
# Usage : bash mix.sh /chemin/vers/la-musique.mp4
#   La musique baisse automatiquement sous la voix (ducking) et remonte entre les phrases ; fondu d'entrée et de sortie ; niveau final -14 LUFS. Musique en lit sonore sous la voix ; elle remonte quand la voix se termine (33,6 s).
set -euo pipefail
cd "$(dirname "$0")"
MUSIC="${1:?Indiquez le fichier de musique, un .mp4 ou un .mp3}"
DUR=40            # durée de la vidéo (s)
VOICE_DELAY=300   # la voix démarre 0,3 s après le début (doit rester égal à OFF dans scenes.js)

ffmpeg -hide_banner -loglevel error -y \
  -i "$MUSIC" -i audio/voice.mp3 -i out/parcours-muet.mp4 \
  -filter_complex "
    [0:a]aformat=channel_layouts=stereo,atrim=0:${DUR},asetpts=PTS-STARTPTS,afade=t=in:st=0:d=0.6,afade=t=out:st=$((DUR-3)):d=3[m];
    [1:a]aformat=channel_layouts=stereo,adelay=${VOICE_DELAY}|${VOICE_DELAY},apad=whole_dur=${DUR},atrim=0:${DUR},asetpts=PTS-STARTPTS,highpass=f=70,acompressor=threshold=-20dB:ratio=3:attack=5:release=120[v];
    [v]asplit=2[vmix][vsc];
    [m][vsc]sidechaincompress=threshold=0.04:ratio=3:attack=20:release=350:makeup=1[md];
    [md]volume=eval=frame:volume='0.30+0.50*clip((t-33.6)/1.2,0,1)'[mq];
    [mq][vmix]amix=inputs=2:normalize=0:duration=first,loudnorm=I=-14:TP=-1.5:LRA=9[a]" \
  -map 2:v -map "[a]" -c:v copy -c:a aac -b:a 192k -t "${DUR}" -movflags +faststart out/cleanup-parcours-client.mp4

echo "OK -> out/cleanup-parcours-client.mp4"
ffprobe -v error -show_entries format=duration:stream=codec_name,width,height,r_frame_rate -of compact out/cleanup-parcours-client.mp4
