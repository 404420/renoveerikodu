(function() {
	'use strict';

	var priceList = [
		{ name: 'kipsplaadi paigaldus', unit: 'm2', price: 16, keywords: ['kips', 'kipsplaat', 'kipsitood', 'karkass', 'vahesein'], note: 'Hind soltub karkassist, kihist, lae/seina keerukusest ja viimistluse tasemest.' },
		{ name: 'pahteldus ja lihvimine', unit: 'm2', price: 9, keywords: ['pahtel', 'pahteldus', 'lihvimine'], note: 'Tapsustamiseks on vaja teada, kas pind on uus kips, vana sein voi parandustega pind.' },
		{ name: 'kruntimine', unit: 'm2', price: 3.5, keywords: ['krunt', 'kruntimine'], note: 'Kruntimine kaib tavaliselt enne varvimist voi plaatimist.' },
		{ name: 'varvimine 2 kihti', unit: 'm2', price: 7.5, keywords: ['varv', 'varvimine', 'maalri', 'maalritoo', 'maalritood', 'seinavärv', 'seina varv'], note: 'Hind soltub ettevalmistusest, toonivahetusest, lagede olemasolust ja pindade seisukorrast.' },
		{ name: 'seinapinna tasandus kuni 10 mm', unit: 'm2', price: 19, materialPrice: 29, keywords: ['tasandus', 'tasandamine', 'seinapinna tasandus'], note: 'Tasanduse vajadus selgub aluspinna ebatasasusest.' },
		{ name: 'poranda tasandus ja kallete valamine', unit: 'm2', price: 19, materialPrice: 29, keywords: ['põranda tasandus', 'poranda tasandus', 'kalle', 'kallete valamine', 'valu'], note: 'Margades ruumides on kalle eriti oluline aravoolu suunas.' },
		{ name: 'hudroisolatsiooni paigaldus', unit: 'm2', price: 14, materialPrice: 24, keywords: ['hudro', 'hüdro', 'hüdroisolatsioon', 'niiskuskaitse'], note: 'Vannitoas ja dushinurgas on hudroisolatsioon enne plaatimist kriitiline.' },
		{ name: 'seinte ja poranda plaatimine', unit: 'm2', price: 49, materialPrice: 59, keywords: ['plaat', 'plaatimine', 'plaatimistood', 'vannitoa plaatimine', 'köögitaust', 'koogitaust'], note: 'Hinda mojutavad plaadi formaat, mustri keerukus, aluspind, nurgad ja silikoon/vuuk.' },
		{ name: 'plaaditud porandaliist', unit: 'jm', price: 19, keywords: ['plaaditud liist', 'põrandaliist plaat', 'porandaliist plaat'], note: 'Jooksva meetri hind soltub loigete ja nurkade arvust.' },
		{ name: 'koogitausta plaatimine', unit: 'komplekt', price: 349, keywords: ['köögitaust', 'koogitaust', 'köögi taust', 'koogi taust'], note: 'Tavaliselt saab esmase hinna fotode ja ligikaudsete mootude pohjal.' },
		{ name: 'laudparketi paigaldus', unit: 'm2', price: 12, keywords: ['laudparkett', 'parkett', 'parketi paigaldus'], note: 'Oluline on aluspinna sirgus, vana poranda eemaldus ja liistude maht.' },
		{ name: 'laminaatparketi paigaldus', unit: 'm2', price: 9, keywords: ['laminaat', 'laminaatparkett'], note: 'Tapsusta, kas vana kate tuleb eemaldada ja kas liistud kuuluvad too sisse.' },
		{ name: 'porandaliistu paigaldus', unit: 'jm', price: 5, keywords: ['liist', 'liistud', 'põrandaliist', 'porandaliist'], note: 'Hinda mojutavad nurgad, seinte sirgus ja liistu tuup.' },
		{ name: 'mittekandva seina lammutus', unit: 'm2', price: 35, keywords: ['lammutus', 'lammutustood', 'seina lammutus', 'demonteerimine'], note: 'Enne lammutust tuleb tapsustada, kas sein on kandev ja kas vaja on prahi aravedu.' },
		{ name: 'segisti paigaldus', unit: 'tk', price: 59, keywords: ['segisti'], note: 'Tapsusta, kas olemasolev torustik ja kinnitused sobivad.' },
		{ name: 'dushisegisti paigaldus', unit: 'tk', price: 69, keywords: ['dushisegisti', 'dušisegisti', 'dussisegisti'], note: 'Hind soltub ligipaasust ja olemasolevast torustikust.' },
		{ name: 'valamu paigaldus', unit: 'tk', price: 59, keywords: ['valamu', 'kraanikauss'], note: 'Vaja on teada, kas paigaldada tuleb ka sifoon, kapp voi segisti.' },
		{ name: 'WC poti paigaldus', unit: 'tk', price: 79, keywords: ['wc', 'pott', 'wc pott', 'tualett'], note: 'Tapsusta, kas tegu on porandapotiga voi seinasisese raamiga.' },
		{ name: 'dushinurga paigaldus', unit: 'tk', price: 139, keywords: ['dushinurk', 'dušinurk', 'dussinurk'], note: 'Hind soltub mudelist, seina sirgusest ja silikoonimise mahust.' },
		{ name: 'fassaadi pesu voi puhastus', unit: 'm2', price: 6, keywords: ['fassaadi pesu', 'fassaadipesu', 'puhastus', 'fassaadi puhastus'], note: 'Hind soltub fassaadi korgusest, ligipaasust ja mustuse liigist.' },
		{ name: 'fassaadi krunt ja varv', unit: 'm2', price: 14, keywords: ['fassaadi varvimine', 'fassaadivarvimine', 'fassaadi krunt', 'puitfassaadi varvimine'], note: 'Oluline on aluspinna seisukord, vana varv, parandused ja tellingu vajadus.' },
		{ name: 'puitfassaadi paigaldus', unit: 'm2', price: 39, keywords: ['puitfassaad', 'fassaadi paigaldus'], note: 'Tapsustada tuleb roovitus, tuuletoke, liitekohad ja viimistlus.' },
		{ name: 'krohvfassaadi susteem', unit: 'm2', price: 49, keywords: ['krohvfassaad', 'krohvimine fassaad', 'soojustus'], note: 'Hind soltub susteemist, soojustusest, aluspinnast ja hoone korgusest.' },
		{ name: 'tapeetimine', unit: 'm2', price: 17, keywords: ['tapeet', 'tapeetimine'], note: 'Hind soltub tapeedi tuubist, mustri sobitamisest ja aluspinna ettevalmistusest.' },
		{ name: 'tapeedi eemaldus', unit: 'm2', price: 3.5, keywords: ['tapeedi eemaldus', 'vana tapeet'], note: 'Vana tapeedi eemaldusel soltub hind kihtide arvust ja liimi tugevusest.' },
		{ name: 'vuukimine ja silikoonimine', unit: 'm2', price: 8, keywords: ['vuuk', 'vuukimine', 'silikoon', 'silikoonimine'], note: 'Margades ruumides on korrektne silikoonimine oluline lekete valtimiseks.' },
		{ name: 'akna poskede viimistlus', unit: 'jm', price: 20, keywords: ['aken', 'akna põsk', 'akna posk', 'aknapaled'], note: 'Hind soltub akende arvust, poskede laiusest ja viimistluse tasemest.' }
	];

	var packages = [
		{ keywords: ['1-toaline', '1 toalise', 'ühetoaline', 'uhetoaline', 'korteri värskendus', 'korteri varskendus'], title: '1-toalise korteri varskendus', price: 2900, text: 'pahteldus, varvimine, liistud ja vaiksemad parandused' },
		{ keywords: ['vannitoa remont', 'vannituba', 'vannitoa tervikremont', 'duširuum', 'dushiruum'], title: 'vannitoa tervikremont', price: 4900, text: 'ettevalmistus, hudroisolatsioon, plaatimine ja loppviimistlus' },
		{ keywords: ['kogu korter', 'korteri remont', 'tervikremont', 'siseviimistlus kogu'], title: 'kogu korteri siseviimistlus', price: 8900, text: 'kips, pahtel, varv voi tapeet, porandad ja detailid' }
	];

	function normalize(text) {
		return String(text || '')
			.toLowerCase()
			.replace(/[õö]/g, 'o')
			.replace(/[ä]/g, 'a')
			.replace(/[ü]/g, 'u')
			.replace(/[š]/g, 's')
			.replace(/[ž]/g, 'z');
	}

	function escapeHtml(text) {
		return String(text).replace(/[&<>"]/g, function(char) {
			return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[char];
		});
	}

	function formatMoney(value) {
		var rounded = Math.round(value * 100) / 100;
		return String(rounded).replace('.', ',') + ' eurot';
	}

	function findAmount(text) {
		var match = normalize(text).match(/(\d+(?:[.,]\d+)?)\s*(m2|m²|ruut|ruutu|jm|jooks|tk|tukk|komplekt)?/);
		return match ? parseFloat(match[1].replace(',', '.')) : null;
	}

	function findServices(text) {
		var normalized = normalize(text);
		return priceList.filter(function(service) {
			return service.keywords.some(function(keyword) {
				return normalized.indexOf(normalize(keyword)) !== -1;
			});
		});
	}

	function findPackage(text) {
		var normalized = normalize(text);
		return packages.find(function(item) {
			return item.keywords.some(function(keyword) {
				return normalized.indexOf(normalize(keyword)) !== -1;
			});
		});
	}

	function hasAny(text, words) {
		var normalized = normalize(text);
		return words.some(function(word) {
			return normalized.indexOf(normalize(word)) !== -1;
		});
	}

	function contactPrompt() {
		return 'Täpse pakkumise jaoks saada palun fotod, mõõdud, aadress või piirkond ning soovitud tööde nimekiri. <a href="kontakt.html#vorm">Ava hinnapäringu vorm</a>.';
	}

	function serviceResponse(service, amount) {
		var html = '<strong>' + escapeHtml(service.name) + '</strong><br>';
		html += 'Hinnakirja orientiir: al ' + formatMoney(service.price) + ' / ' + service.unit + '.';
		if (service.materialPrice) {
			html += '<br>Koos tavapärase materjaliga: al ' + formatMoney(service.materialPrice) + ' / ' + service.unit + '.';
		}
		if (amount) {
			html += '<br>Ligikaudne tööraha ' + amount + ' ' + service.unit + ' puhul: <strong>al ' + formatMoney(amount * service.price) + '</strong>.';
			if (service.materialPrice) {
				html += '<br>Koos tavapärase materjaliga ligikaudu: <strong>al ' + formatMoney(amount * service.materialPrice) + '</strong>.';
			}
		} else {
			html += '<br>Kui kirjutad koguse, näiteks "25 m2", arvutan kiire esmase vahemiku.';
		}
		html += '<br>' + escapeHtml(service.note);
		html += '<br><br>' + contactPrompt();
		return html;
	}

	function combinedResponse(services, amount) {
		var total = 0;
		var html = '<strong>Leidsin mitu tööd hinnakirjast:</strong><br>';
		services.slice(0, 5).forEach(function(service) {
			html += '- ' + escapeHtml(service.name) + ': al ' + formatMoney(service.price) + ' / ' + service.unit + '<br>';
			if (amount && service.unit === 'm2') total += amount * service.price;
		});
		if (amount && total) {
			html += '<br>Kui kõigi m2 tööde kogus on umbes ' + amount + ' m2, siis tööraha orientiir on <strong>al ' + formatMoney(total) + '</strong>.';
		}
		html += '<br><br>Kui tööde kogused on erinevad, kirjuta näiteks: "pahteldus 40 m2, värvimine 40 m2, parkett 25 m2". ' + contactPrompt();
		return html;
	}

	function answer(text, state) {
		var normalized = normalize(text);
		var amount = findAmount(text);
		var services = findServices(text);
		var pack = findPackage(text);

		if (services[0]) state.pendingService = services[0];
		if (amount) state.pendingAmount = amount;

		if (hasAny(text, ['tere', 'hei', 'jou', 'hello'])) {
			return 'Tere! Saan aidata renoveerimistööde hinnakirja, tööde järjekorra ja pakkumise ettevalmistusega. Kirjuta näiteks "vannitoa plaatimine 20 m2", "korteri värvimine" või "mida pakkumiseks vaja on?".';
		}

		if (hasAny(text, ['aitah', 'aitäh', 'tänud', 'tanud'])) {
			return 'Hea meelega. Kui saadad töö kirjelduse, mõõdud ja fotod, saab hinnangu juba palju täpsemaks teha. ' + contactPrompt();
		}

		if (hasAny(text, ['kontakt', 'telefon', 'email', 'meil', 'ühendus', 'uhendus', 'pakkumine', 'pakkumist', 'päring', 'paring'])) {
			return contactPrompt() + '<br>Fotod ja mõõdud aitavad meil vältida liiga üldist hinda ning anda realistlikuma pakkumise.';
		}

		if (hasAny(text, ['mida vaja', 'mis vaja', 'mida saata', 'fotod', 'mõõdud', 'moodud'])) {
			return 'Kõige parem päring sisaldab: 1) fotosid praegusest olukorrast, 2) ligikaudseid mõõte, 3) aadressi või piirkonda, 4) soovitud lõpptulemust, 5) infot, kas materjalid on olemas või vajavad soovitust. ' + contactPrompt();
		}

		if (hasAny(text, ['piirkond', 'kus teete', 'tallinn', 'harjumaa'])) {
			return 'Töötame peamiselt Tallinnas ja Harjumaal. Kui objekt jääb mujale, kirjuta asukoht ning saame hinnata, kas töö sobib graafikusse.';
		}

		if (hasAny(text, ['materjal', 'materjalid', 'sisaldab materjali', 'koos materjaliga'])) {
			return 'Hinnakirjas on osa töödel eraldi näidatud töö hind ja hind koos tavapärase materjaliga. Lõplik materjalikulu sõltub tootest, aluspinnast ja töömahust. Plaatimisel näiteks mõjutavad hinda plaatide formaat, hüdroisolatsioon, tasandus, vuuk ja silikoon.';
		}

		if (hasAny(text, ['garantii', 'garantee', 'vastutus'])) {
			return 'Töö kvaliteet sõltub õigest ettevalmistusest ja materjalidest. Pakkumises saab täpsustada tööde sisu, materjalid ja garantii tingimused konkreetse objekti järgi.';
		}

		if (hasAny(text, ['kaua', 'aeg', 'kestab', 'millal'])) {
			return 'Töö kestus sõltub mahust ja kuivamisaegadest. Väike maalritöö võib võtta 1-3 päeva, vannitoa tervikremont tihti mitu nädalat. Kui saadad fotod ja mõõdud, saab realistlikuma ajakava öelda.';
		}

		if (pack) {
			return '<strong>' + escapeHtml(pack.title) + '</strong><br>Hinnakirja näidispakett: al ' + formatMoney(pack.price) + '. Tavaliselt sisaldab see: ' + escapeHtml(pack.text) + '. Täpne hind sõltub seisukorrast, materjalidest ja töömahust.<br><br>' + contactPrompt();
		}

		if (services.length > 1) return combinedResponse(services, amount || state.pendingAmount);
		if (services.length === 1) return serviceResponse(services[0], amount || state.pendingAmount);
		if (state.pendingService && amount) return serviceResponse(state.pendingService, amount);

		if (hasAny(text, ['hind', 'maksab', 'palju', 'hinnakiri', 'euro'])) {
			return 'Saan arvutada esmase orientiiri hinnakirja järgi. Kirjuta töö ja kogus, näiteks "plaatimine 20 m2", "parkett 35 m2", "WC poti paigaldus 1 tk" või "fassaadi värvimine 80 m2".';
		}

		return 'Saan aidata hinnakirja ja renoveerimistööde küsimustega: plaatimine, maalritööd, kipsitööd, parkett, lammutus, sanitaar, fassaad ja vannituba. Kirjuta töö nimetus ja võimalusel kogus, siis annan esmase hinnangu. ' + contactPrompt();
	}

	function init() {
		var bubble = document.getElementById('ai-bubble');
		var chat = document.getElementById('ai-chat');
		var close = document.getElementById('ai-close');
		var input = document.getElementById('ai-input');
		var sendBtn = document.getElementById('ai-send');
		var messages = document.getElementById('ai-messages');
		var state = { pendingService: null, pendingAmount: null };

		if (!bubble || !chat || !close || !input || !sendBtn || !messages) return;
		if (chat.dataset.renoveeriAiReady === '1') return;
		chat.dataset.renoveeriAiReady = '1';

		messages.innerHTML = '<p><b>AI:</b> Tere! Olen Renoveeri Kodu abiline. Küsi hinnakirja, tööde järjekorra või pakkumise kohta. Näiteks: "plaatimine 20 m2" või "mida pakkumiseks vaja on?".</p>';

		function openChat(event) {
			if (event) event.preventDefault();
			chat.style.display = 'flex';
			bubble.style.display = 'none';
			input.focus();
		}

		function closeChat(event) {
			if (event) event.preventDefault();
			chat.style.display = 'none';
			bubble.style.display = 'flex';
		}

		function addMessage(sender, html) {
			var p = document.createElement('p');
			p.innerHTML = '<b>' + sender + ':</b> ' + html;
			messages.appendChild(p);
			messages.scrollTop = messages.scrollHeight;
		}

		function sendMessage(event) {
			if (event) event.preventDefault();
			var text = input.value.trim();
			if (!text) return;
			addMessage('Klient', escapeHtml(text));
			input.value = '';
			window.setTimeout(function() {
				addMessage('AI', answer(text, state));
			}, 180);
		}

		bubble.onclick = openChat;
		close.onclick = closeChat;
		sendBtn.onclick = sendMessage;
		input.addEventListener('keydown', function(event) {
			if (event.key === 'Enter') sendMessage(event);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
