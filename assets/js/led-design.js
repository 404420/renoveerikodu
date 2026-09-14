(function () {
  'use strict';
  var room = document.querySelector('.led-room');
  var label = document.getElementById('led-kelvin');
  var description = document.querySelector('.led-tone-description');
  var buttons = document.querySelectorAll('[data-led-tone]');
  if (!room || !label || !description) return;
  var tones = {
    '2700': '2700 K — eriti soe ja hubane valgus rahulikeks õhtuteks ning puhkealadeks.',
    '3000': '3000 K — soe ja tasakaalukas valgus elutuppa, magamistuppa või trepile.',
    '4000': '4000 K — neutraalsem valgus kööki ja tööpindadele, kus detailide nägemine on oluline.'
  };
  buttons.forEach(function (button) {
    button.addEventListener('click', function () {
      var tone = button.getAttribute('data-led-tone');
      if (!tones[tone]) return;
      buttons.forEach(function (item) { item.setAttribute('aria-pressed', String(item === button)); });
      room.setAttribute('data-tone', tone);
      room.setAttribute('aria-label', 'Illustratsioon ' + tone + ' K kaudvalgusest ruumis');
      label.textContent = tone + ' K';
      description.textContent = tones[tone];
    });
  });
})();
