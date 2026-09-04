/**
 * API de Sonidos Sintetizados para Petulap SST
 * No requiere archivos MP3/WAV, genera las ondas matematicamente usando Web Audio API.
 */

// Inicializamos el contexto de audio
const AudioContext = window.AudioContext || window.webkitAudioContext;
let audioCtx;

function initAudio() {
  if (!audioCtx) {
    audioCtx = new AudioContext();
  }
  // Los navegadores suspenden el audio hasta que el usuario interactua
  if (audioCtx.state === 'suspended') {
    audioCtx.resume();
  }
}

// Funcion principal para disparar sonidos
function playSound(type) {
  initAudio();
  if (!audioCtx) return;

  const now = audioCtx.currentTime;
  const osc = audioCtx.createOscillator();
  const gainNode = audioCtx.createGain();

  osc.connect(gainNode);
  gainNode.connect(audioCtx.destination);

  // Volumen base subido x2 o x3 en comparacion a antes
  switch (type) {
    case 'click':
      osc.type = 'sine';
      osc.frequency.setValueAtTime(600, now);
      osc.frequency.exponentialRampToValueAtTime(300, now + 0.05);
      gainNode.gain.setValueAtTime(0.3, now);
      gainNode.gain.exponentialRampToValueAtTime(0.01, now + 0.05);
      osc.start(now);
      osc.stop(now + 0.05);
      break;

    case 'success':
      osc.type = 'triangle';
      osc.frequency.setValueAtTime(523.25, now); // C5
      osc.frequency.setValueAtTime(659.25, now + 0.1); // E5
      gainNode.gain.setValueAtTime(0.3, now);
      gainNode.gain.linearRampToValueAtTime(0, now + 0.3);
      osc.start(now);
      osc.stop(now + 0.3);
      break;

    case 'error':
      osc.type = 'sawtooth';
      osc.frequency.setValueAtTime(150, now);
      osc.frequency.linearRampToValueAtTime(100, now + 0.2);
      gainNode.gain.setValueAtTime(0.3, now);
      gainNode.gain.linearRampToValueAtTime(0, now + 0.2);
      osc.start(now);
      osc.stop(now + 0.2);
      break;
      
    case 'scan':
      // Sonido rapido y agudo de pistola de codigo de barras
      osc.type = 'square';
      osc.frequency.setValueAtTime(1200, now); 
      osc.frequency.setValueAtTime(2000, now + 0.05); 
      gainNode.gain.setValueAtTime(0.8, now); // Volumen aumentado de 0.15 a 0.8
      gainNode.gain.exponentialRampToValueAtTime(0.01, now + 0.15);
      osc.start(now);
      osc.stop(now + 0.15);
      break;
      
    case 'scan_error':
      // Sonido de alerta muy fuerte (tipo chicharra de error)
      osc.type = 'sawtooth';
      osc.frequency.setValueAtTime(200, now);
      osc.frequency.setValueAtTime(150, now + 0.1);
      osc.frequency.setValueAtTime(200, now + 0.2);
      osc.frequency.setValueAtTime(150, now + 0.3);
      osc.frequency.setValueAtTime(200, now + 0.4);
      gainNode.gain.setValueAtTime(1.0, now); // Volumen MAXIMO
      gainNode.gain.linearRampToValueAtTime(0, now + 0.5);
      osc.start(now);
      osc.stop(now + 0.5);
      break;

    case 'notification':
      // Alerta fuerte para nuevo ticket (Sirena o campanada intensa)
      osc.type = 'square';
      osc.frequency.setValueAtTime(880, now);
      osc.frequency.setValueAtTime(1108.73, now + 0.2); 
      osc.frequency.setValueAtTime(1318.51, now + 0.4); 
      osc.frequency.setValueAtTime(1760, now + 0.6); 
      gainNode.gain.setValueAtTime(0.4, now);
      gainNode.gain.exponentialRampToValueAtTime(0.01, now + 1.2);
      
      const osc2 = audioCtx.createOscillator();
      const gain2 = audioCtx.createGain();
      osc2.type = 'sine';
      osc2.frequency.setValueAtTime(1760, now);
      gain2.gain.setValueAtTime(0.2, now);
      gain2.gain.exponentialRampToValueAtTime(0.01, now + 1.2);
      osc2.connect(gain2);
      gain2.connect(audioCtx.destination);
      
      osc.start(now);
      osc.stop(now + 1.2);
      osc2.start(now);
      osc2.stop(now + 1.2);
      break;
  }
}

// Auto-enlazar sonidos a botones de la web
document.addEventListener('DOMContentLoaded', () => {
  const unlockAudio = () => { initAudio(); };
  document.body.addEventListener('click', unlockAudio);
  document.body.addEventListener('keydown', unlockAudio);
  document.body.addEventListener('touchstart', unlockAudio);

  document.body.addEventListener('click', (e) => {
    if (e.target.tagName === 'BUTTON' || e.target.closest('button')) {
      const btn = e.target.tagName === 'BUTTON' ? e.target : e.target.closest('button');
      if (btn.classList.contains('btn-primary')) {
        playSound('click');
      } else if (btn.classList.contains('btn-green') || btn.classList.contains('btn-outline')) {
        playSound('success');
      } else if (btn.classList.contains('btn-danger') || btn.classList.contains('btn-warn')) {
        playSound('error');
      } else {
        playSound('click');
      }
    }
  });
});
