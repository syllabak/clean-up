// Design tokens + timing constants (single source of truth).
const FPS = 30;
const DURATION = 16; // seconds
const W = 1080;
const H = 1920;

const COLOR = {
  navy: '#0B2545',   // = theme_color de la PWA
  navy2: '#13315C',
  cyan: '#2EC4F1',
  cyanDeep: '#1486B5',
  mint: '#8EE3C8',
  paper: '#F6FAFD',
  mist: '#EAF1F7',
  mudBg: '#2A231E',
  mudText: '#CBB89F',
  sky: '#B8CCE0',
};

// Beat sheet (seconds). Chaque scène lit uniquement ces valeurs.
const T = {
  s1: { in: 0.0, wipe: 1.35, wipeDur: 0.75, out: 3.4 },
  s2: { circle: 2.55, mark: 3.05, word: 3.45, tag: 4.0, exit: 4.55, end: 5.0 },
  s3: { in: 4.6, cards: 4.95, cardGap: 0.28, exit: 9.0, end: 9.7 },
  s4: { in: 9.15, steps: [[9.9, 10.7], [10.7, 11.5], [11.5, 12.3]], success: 12.3, exit: 13.0, end: 13.8 },
  s5: { circle: 13.0, content: 13.6, pulse: 14.8 },
};
