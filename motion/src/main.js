buildScenes();
window.__duration = DURATION;
window.__fps = FPS;
window.__render = (t) => { renderFrame(t); };

const params = new URLSearchParams(location.search);
if (!params.has('render')) {
  // Aperçu : mise à l'échelle + lecture en boucle
  const fit = () => {
    const k = Math.min(innerWidth / W, innerHeight / H);
    const st = $('stage');
    st.style.transform = `translate(${(innerWidth - W * k) / 2}px,${(innerHeight - H * k) / 2}px) scale(${k})`;
  };
  addEventListener('resize', fit); fit();
  const start = performance.now();
  const loop = (now) => {
    renderFrame(params.has('t') ? parseFloat(params.get('t')) : ((now - start) / 1000) % (DURATION + 0.5));
    requestAnimationFrame(loop);
  };
  requestAnimationFrame(loop);
} else {
  renderFrame(0);
}
