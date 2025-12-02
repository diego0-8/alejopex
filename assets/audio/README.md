# Archivos de Audio para el Softphone

Esta carpeta debe contener los siguientes archivos de audio:

## Archivos Requeridos

1. **ringtone.mp3** - Tono de llamada entrante
   - Se reproduce cuando llega una llamada entrante
   - Debe ser un archivo de audio en formato MP3
   - Recomendación: Duración de 2-4 segundos, en loop

2. **ringback.mp3** - Tono de espera (ringback)
   - Se reproduce cuando estás llamando a alguien y está sonando
   - Debe ser un archivo de audio en formato MP3
   - Recomendación: Duración de 2-4 segundos, en loop

## Formatos Soportados

El navegador soporta los siguientes formatos:
- MP3 (recomendado para máxima compatibilidad)
- OGG (alternativa)
- WAV (no recomendado por el tamaño del archivo)

## Recomendaciones

- **Duración**: 2-4 segundos (se reproducen en loop)
- **Volumen**: Ajustado a 0.5 (50%) en el código, puedes modificar si es necesario
- **Calidad**: 128 kbps es suficiente para tonos
- **Frecuencia de muestreo**: 44.1 kHz

## Obtener Archivos de Audio

Puedes descargar tonos gratuitos de:
- https://freesound.org
- https://www.zapsplat.com
- https://mixkit.co/free-sound-effects/phone/

O usar herramientas online para generar tonos telefónicos:
- Generadores de tonos DTMF
- Sintetizadores de audio online

## Nota

Si los archivos no existen, el softphone funcionará normalmente pero sin sonidos de tonos.




