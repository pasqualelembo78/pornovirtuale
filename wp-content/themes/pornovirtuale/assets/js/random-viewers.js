 
document.querySelectorAll('.random-viewers').forEach(el => {
  const min = parseInt(el.getAttribute('data-min'));
  const max = parseInt(el.getAttribute('data-max'));
  let count = Math.floor(Math.random() * (max - min + 1)) + min;
  if(count > 999){ count = (count/1000).toFixed(1) + 'k'; }
  el.textContent = count;
});