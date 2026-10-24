(function() {
  const viewersSpan = document.getElementById("live-viewers");

  if (!viewersSpan) {
    console.error("random-viewers-count.js: #live-viewers non trovato.");
    return;
  }

  function formatNumber(num) {
    if (num >= 1000) return (num / 1000).toFixed(1) + "k";
    return num.toString();
  }

  function updateViewers() {
    // numero casuale tra min e max (puoi cambiare range)
    const min = 800;
    const max = 2500;
    const randomViewers = Math.floor(Math.random() * (max - min + 1)) + min;
    viewersSpan.textContent = formatNumber(randomViewers);
  }

  // Aggiorna subito
  updateViewers();

  // Aggiorna ogni 10 secondi
  setInterval(updateViewers, 10000);

})();