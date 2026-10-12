(function () {
  'use strict';
  var language = (document.documentElement.lang || 'et').toLowerCase().split('-')[0];
  if (['et', 'en', 'ru'].indexOf(language) === -1) language = 'et';
  var messages = {
    et: {
      apartment: 'Korteri remont', menuClose: 'Sulge menüü', sending: 'Saadan…',
      inquirySending: 'Saadan päringut…', file: 'fail', removeFile: 'Eemalda fail {name}',
      captchaMissing: 'Turvakontrolli ei õnnestunud laadida. Palun värskenda lehte või võta meiega ühendust telefoni või e-posti teel.',
      captchaRequired: 'Palun kinnita, et sa ei ole robot.',
      sendFailed: 'Päringu saatmine ebaõnnestus. Palun proovi hiljem uuesti.',
      sendSuccess: 'Päring saadetud. Võtame sinuga ühendust.',
      serverError: 'Ühendus serveriga ebaõnnestus. Palun proovi hiljem uuesti.',
      led2700: '2700 K — eriti soe ja hubane valgus rahulikeks õhtuteks ning puhkealadeks.',
      led3000: '3000 K — soe ja tasakaalukas valgus elutuppa, magamistuppa või trepile.',
      led4000: '4000 K — neutraalsem valgus kööki ja tööpindadele, kus detailide nägemine on oluline.',
      ledRoom: 'Illustratsioon {tone} K kaudvalgusest ruumis'
    },
    en: {
      apartment: 'Apartment renovation', menuClose: 'Close menu', sending: 'Sending…',
      inquirySending: 'Sending your enquiry…', file: 'file', removeFile: 'Remove file {name}',
      captchaMissing: 'The security check could not be loaded. Please refresh the page or contact us by phone or email.',
      captchaRequired: 'Please confirm that you are not a robot.',
      sendFailed: 'Your enquiry could not be sent. Please try again later.',
      sendSuccess: 'Your enquiry has been sent. We will contact you.',
      serverError: 'Could not connect to the server. Please try again later.',
      led2700: '2700 K — extra warm, cosy lighting for quiet evenings and relaxation areas.',
      led3000: '3000 K — warm, balanced lighting for living rooms, bedrooms and stairs.',
      led4000: '4000 K — neutral lighting for kitchens and worktops where seeing details matters.',
      ledRoom: 'Illustration of {tone} K indirect lighting in a room'
    },
    ru: {
      apartment: 'Ремонт квартиры', menuClose: 'Закрыть меню', sending: 'Отправка…',
      inquirySending: 'Отправляем ваш запрос…', file: 'файл', removeFile: 'Удалить файл {name}',
      captchaMissing: 'Не удалось загрузить проверку безопасности. Обновите страницу или свяжитесь с нами по телефону или электронной почте.',
      captchaRequired: 'Подтвердите, что вы не робот.',
      sendFailed: 'Не удалось отправить запрос. Попробуйте ещё раз позже.',
      sendSuccess: 'Запрос отправлен. Мы свяжемся с вами.',
      serverError: 'Не удалось подключиться к серверу. Попробуйте ещё раз позже.',
      led2700: '2700 K — очень тёплый и уютный свет для спокойных вечеров и зон отдыха.',
      led3000: '3000 K — тёплый и сбалансированный свет для гостиной, спальни или лестницы.',
      led4000: '4000 K — нейтральный свет для кухни и рабочих поверхностей, где важны детали.',
      ledRoom: 'Иллюстрация непрямого освещения {tone} K в комнате'
    }
  };
  var routes = {
    et: { apartment: '/korteri-remont/', contact: '/kontakt' },
    en: { apartment: '/en/apartment-renovation/', contact: '/en/contact' },
    ru: { apartment: '/ru/remont-kvartir/', contact: '/ru/kontakty' }
  };
  window.RenoveeriI18n = {
    language: language,
    t: function (key, values) {
      var value = messages[language][key] || messages.et[key] || key;
      return value.replace(/\{(\w+)\}/g, function (match, name) {
        return values && Object.prototype.hasOwnProperty.call(values, name) ? String(values[name]) : match;
      });
    },
    route: function (key) { return routes[language][key] || routes.et[key] || '/'; }
  };
})();
