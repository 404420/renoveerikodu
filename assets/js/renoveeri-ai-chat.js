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

	var language = window.RenoveeriI18n ? window.RenoveeriI18n.language : (document.documentElement.lang || 'et').split('-')[0];
	if (['et', 'en', 'ru'].indexOf(language) === -1) language = 'et';
	// Names, explanations and keywords follow the same price rows in every language.
	// Numeric prices remain in the single source list above.
	var localizedServices = {
		en: [
			['drywall installation', 'The price depends on framing, layers, wall or ceiling complexity and the finish required.', ['drywall', 'plasterboard', 'gypsum board', 'partition wall']],
			['filling and sanding', 'Please specify whether the surface is new plasterboard, an old wall or a repaired surface.', ['filling', 'sanding', 'wall filler', 'putty', 'skim coating']],
			['priming', 'Priming is usually required before painting or tiling.', ['primer', 'priming']],
			['painting, two coats', 'The price depends on preparation, colour changes, ceilings and the condition of the surfaces.', ['paint', 'painting', 'decorating']],
			['wall levelling up to 10 mm', 'Levelling requirements depend on how uneven the substrate is.', ['wall levelling', 'wall leveling', 'level walls']],
			['floor levelling and forming falls', 'In wet rooms, the floor must slope correctly towards the drain.', ['floor levelling', 'floor leveling', 'floor screed', 'drainage slope']],
			['waterproofing installation', 'Waterproofing before tiling is essential in bathrooms and shower areas.', ['waterproof', 'waterproofing', 'moisture protection']],
			['wall and floor tiling', 'Tile size, pattern, substrate, corners, grout and silicone affect the price.', ['tiling', 'tile installation', 'tiles']],
			['tiled skirting', 'The price per linear metre depends on cuts and corners.', ['tiled skirting', 'tile skirting']],
			['kitchen splashback tiling', 'An initial estimate can usually be based on photos and approximate dimensions.', ['kitchen splashback', 'backsplash']],
			['engineered wood flooring installation', 'The substrate, removal of the old flooring and skirting affect the work.', ['parquet', 'wood flooring', 'engineered wood']],
			['laminate flooring installation', 'Please specify whether old flooring needs removing and skirting is included.', ['laminate']],
			['skirting board installation', 'Corners, wall alignment and the type of skirting affect the price.', ['skirting board', 'baseboard']],
			['non-load-bearing wall demolition', 'First check whether the wall is load-bearing and whether waste removal is required.', ['demolition', 'demolish', 'dismantling', 'remove wall']],
			['tap installation', 'Please check whether the existing pipework and fittings are suitable.', ['tap installation', 'faucet installation', 'mixer tap']],
			['shower mixer installation', 'The price depends on access and the existing pipework.', ['shower mixer', 'shower tap']],
			['washbasin installation', 'Please specify whether a trap, vanity unit or tap also needs installing.', ['washbasin', 'sink installation']],
			['toilet installation', 'Please specify whether this is a floor-standing toilet or a concealed frame.', ['toilet', 'wc']],
			['shower enclosure installation', 'The price depends on the model, wall alignment and silicone sealing.', ['shower enclosure', 'shower cabin']],
			['facade washing or cleaning', 'The price depends on building height, access and the type of dirt.', ['facade washing', 'facade cleaning', 'exterior cleaning']],
			['facade priming and painting', 'Substrate condition, old paint, repairs and scaffolding affect the work.', ['facade painting', 'exterior painting', 'facade priming']],
			['timber facade installation', 'Battens, windproofing, joints and finishing need to be specified.', ['timber facade', 'wood cladding']],
			['rendered facade system', 'The price depends on the system, insulation, substrate and building height.', ['rendered facade', 'facade rendering', 'external insulation']],
			['wallpapering', 'The wallpaper type, pattern matching and surface preparation affect the price.', ['wallpapering', 'wallpaper installation']],
			['wallpaper removal', 'The price depends on the number of old wallpaper layers and the strength of the adhesive.', ['wallpaper removal', 'remove wallpaper', 'old wallpaper']],
			['grouting and silicone sealing', 'Correct silicone sealing helps prevent leaks in wet rooms.', ['grouting', 'grout', 'silicone', 'sealing']],
			['window reveal finishing', 'The price depends on the number and width of the reveals and the finish required.', ['window reveal', 'window finishing']]
		],
		ru: [
			['монтаж гипсокартона', 'Цена зависит от каркаса, количества слоёв, сложности стен или потолка и уровня отделки.', ['гипсокартон', 'гипсокартонные', 'перегородка', 'каркас']],
			['шпаклёвка и шлифовка', 'Уточните, что нужно отделывать: новый гипсокартон, старую стену или поверхность после ремонта.', ['шпаклев', 'шпаклёв', 'шпаклов', 'шпатлев', 'шлифов']],
			['грунтование', 'Грунтование обычно выполняют перед покраской или укладкой плитки.', ['грунт']],
			['покраска в два слоя', 'Цена зависит от подготовки, смены цвета, наличия потолков и состояния поверхностей.', ['покраск', 'окраск', 'маляр']],
			['выравнивание стен до 10 мм', 'Необходимость выравнивания зависит от неровностей основания.', ['выравнивание стен', 'выравнивать стен']],
			['выравнивание пола и формирование уклонов', 'Во влажных помещениях особенно важен правильный уклон к сливу.', ['выравнивание пола', 'стяжка', 'уклон']],
			['монтаж гидроизоляции', 'В ванной и душевой гидроизоляция перед укладкой плитки обязательна.', ['гидроизоляц', 'влагозащит']],
			['укладка плитки на стены и пол', 'На цену влияют формат плитки, рисунок, основание, углы, затирка и силикон.', ['плитк', 'плиточ']],
			['плинтус из плитки', 'Цена погонного метра зависит от количества подрезок и углов.', ['плинтус из плитки', 'плиточный плинтус']],
			['укладка кухонного фартука', 'Предварительную оценку обычно можно дать по фотографиям и примерным размерам.', ['кухонный фартук', 'фартук кухни']],
			['укладка паркетной доски', 'Важны ровность основания, демонтаж старого пола и объём установки плинтусов.', ['паркет', 'паркетная доска']],
			['укладка ламината', 'Уточните, нужно ли снять старое покрытие и входит ли установка плинтусов в работу.', ['ламинат']],
			['установка напольного плинтуса', 'На цену влияют углы, ровность стен и тип плинтуса.', ['плинтус']],
			['демонтаж ненесущей стены', 'Перед демонтажем нужно уточнить, является ли стена несущей и нужен ли вывоз мусора.', ['демонтаж', 'снос', 'разборка']],
			['установка смесителя', 'Уточните, подходят ли существующие трубы и крепления.', ['смесител']],
			['установка душевого смесителя', 'Цена зависит от доступа и существующих труб.', ['душевой смеситель', 'смеситель для душа']],
			['установка раковины', 'Нужно знать, требуется ли также установка сифона, тумбы или смесителя.', ['раковин', 'умывальник']],
			['установка унитаза', 'Уточните, идёт ли речь о напольном унитазе или инсталляции.', ['унитаз', 'туалет', 'wc']],
			['установка душевого уголка', 'Цена зависит от модели, ровности стен и объёма герметизации силиконом.', ['душевой уголок', 'душевая кабина', 'душевой кабины']],
			['мойка или очистка фасада', 'Цена зависит от высоты фасада, доступа и типа загрязнения.', ['мойка фасада', 'мытье фасада', 'мытьё фасада', 'очистка фасада']],
			['грунтование и покраска фасада', 'Важны состояние основания, старой краски, необходимый ремонт и потребность в лесах.', ['покраска фасада', 'окраска фасада', 'грунтование фасада']],
			['монтаж деревянного фасада', 'Нужно уточнить обрешётку, ветрозащиту, стыки и отделку.', ['деревянный фасад', 'монтаж деревянного фасада']],
			['штукатурная фасадная система', 'Цена зависит от системы, утепления, основания и высоты здания.', ['штукатурный фасад', 'штукатурка фасада', 'утепление фасада']],
			['поклейка обоев', 'Цена зависит от типа обоев, совмещения рисунка и подготовки основания.', ['поклейка обоев', 'поклеить обои', 'обои']],
			['снятие обоев', 'Цена зависит от количества слоёв старых обоев и прочности клея.', ['снятие обоев', 'снять обои', 'удаление обоев', 'старые обои']],
			['затирка швов и герметизация силиконом', 'Во влажных помещениях правильная герметизация важна для предотвращения протечек.', ['затирк', 'силикон', 'герметизац']],
			['отделка оконных откосов', 'Цена зависит от количества окон, ширины откосов и уровня отделки.', ['откос', 'отделка окна']]
		]
	};
	priceList.forEach(function (service, index) {
		['en', 'ru'].forEach(function (locale) {
			service.keywords = service.keywords.concat(localizedServices[locale][index][2]);
		});
		if (localizedServices[language]) {
			service.name = localizedServices[language][index][0];
			service.note = localizedServices[language][index][1];
		}
	});
	var localizedPackages = {
		en: [
			['one-room apartment refresh', 'filling, painting, skirting and small repairs', ['one-room apartment', 'one room apartment', 'studio refresh', 'apartment refresh']],
			['complete bathroom renovation', 'preparation, waterproofing, tiling and final finishing', ['bathroom renovation', 'bathroom', 'shower room renovation']],
			['complete apartment interior finishing', 'drywall, filling, painting or wallpaper, floors and details', ['whole apartment', 'apartment renovation', 'complete renovation']]
		],
		ru: [
			['обновление однокомнатной квартиры', 'шпаклёвка, покраска, плинтусы и мелкий ремонт', ['однокомнат', 'обновление квартиры', 'косметический ремонт']],
			['полный ремонт ванной', 'подготовка, гидроизоляция, плитка и финишная отделка', ['ремонт ванной', 'ванная', 'ванной', 'ремонт душевой']],
			['полная внутренняя отделка квартиры', 'гипсокартон, шпаклёвка, краска или обои, полы и детали', ['вся квартира', 'всей квартиры', 'ремонт квартиры', 'капитальный ремонт']]
		]
	};
	packages.forEach(function (item, index) {
		['en', 'ru'].forEach(function (locale) {
			item.keywords = item.keywords.concat(localizedPackages[locale][index][2]);
		});
		if (localizedPackages[language]) {
			item.title = localizedPackages[language][index][0];
			item.text = localizedPackages[language][index][1];
		}
	});
	var messagesByLanguage = {
		et: {
			title: 'Küsi hinnangut', open: 'Ava hinnakalkulaator', close: 'Sulge hinnakalkulaator', send: 'Saada', client: 'Klient',
			placeholder: 'Näiteks: plaatimine 20 m²',
			welcome: 'Tere! Olen Renoveeri Kodu abiline. Küsi hinnakirja, tööde järjekorra või pakkumise kohta. Näiteks: „plaatimine 20 m²” või „mida pakkumiseks vaja on?”.',
			greeting: 'Tere! Saan aidata renoveerimistööde hinnakirja, tööde järjekorra ja pakkumise ettevalmistusega. Kirjuta näiteks „vannitoa plaatimine 20 m²”, „korteri värvimine” või „mida pakkumiseks vaja on?”.',
			thanks: 'Hea meelega. Töö kirjeldus, mõõdud ja fotod aitavad hinnangut täpsustada.',
			contact: 'Täpse pakkumise jaoks saada palun fotod, mõõdud, aadress või piirkond ning soovitud tööde nimekiri.',
			contactLink: 'Ava hinnapäringu vorm',
			quote: 'Fotod ja mõõdud aitavad anda realistlikuma pakkumise.',
			information: 'Kõige parem päring sisaldab: 1) fotosid praegusest olukorrast, 2) ligikaudseid mõõte, 3) aadressi või piirkonda, 4) soovitud lõpptulemust, 5) infot, kas materjalid on olemas või vajavad soovitust.',
			region: 'Töötame peamiselt Tallinnas ja Harjumaal. Kui objekt jääb mujale, kirjuta asukoht ning saame hinnata, kas töö sobib graafikusse.',
			materials: 'Hinnakirjas on osa töödel eraldi töö hind ja hind koos tavapärase materjaliga. Lõplik materjalikulu sõltub tootest, aluspinnast ja töömahust. Plaatimisel mõjutavad hinda ka plaatide formaat, hüdroisolatsioon, tasandus, vuuk ja silikoon.',
			warranty: 'Töö kvaliteet sõltub õigest ettevalmistusest ja materjalidest. Pakkumises täpsustame tööde sisu, materjalid ja garantii tingimused konkreetse objekti järgi.',
			time: 'Töö kestus sõltub mahust ja kuivamisaegadest. Väike maalritöö võib võtta 1–3 päeva, vannitoa tervikremont tihti mitu nädalat. Fotode ja mõõtude põhjal saame anda realistlikuma ajakava.',
			pricePrompt: 'Saan arvutada esmase orientiiri hinnakirja järgi. Kirjuta töö ja kogus, näiteks „plaatimine 20 m²”, „parkett 35 m²”, „WC poti paigaldus 1 tk” või „fassaadi värvimine 80 m²”.',
			fallback: 'Saan aidata hinnakirja ja renoveerimistööde küsimustega: plaatimine, maalritööd, kipsitööd, parkett, lammutus, sanitaar, fassaad ja vannituba. Kirjuta töö nimetus ja võimalusel kogus, siis annan esmase hinnangu.',
			price: 'Hinnakirja orientiir: al {price} / {unit}.', materialPrice: 'Koos tavapärase materjaliga: al {price} / {unit}.',
			estimate: 'Ligikaudne tööraha {amount} {unit} puhul: <strong>al {price}</strong>.', materialEstimate: 'Koos tavapärase materjaliga ligikaudu: <strong>al {price}</strong>.',
			amountPrompt: 'Kui kirjutad koguse, näiteks „25 m²”, arvutan kiire esmase hinnangu.', multiple: 'Leidsin mitu tööd hinnakirjast:', from: 'al',
			combined: 'Kui kõigi m² tööde kogus on umbes {amount} m², siis tööraha orientiir on <strong>al {price}</strong>.',
			differentAmounts: 'Kui tööde kogused on erinevad, kirjuta näiteks: „pahteldus 40 m², värvimine 40 m², parkett 25 m²”.',
			package: 'Hinnakirja näidispakett: al {price}. Tavaliselt sisaldab see: {items}. Täpne hind sõltub seisukorrast, materjalidest ja töömahust.'
		},
		en: {
			title: 'Get an estimate', open: 'Open price calculator', close: 'Close price calculator', send: 'Send', client: 'Customer',
			placeholder: 'For example: tiling 20 m²',
			welcome: 'Hello! I am the Renoveeri Kodu assistant. Ask about prices, the order of work or preparing a quote. For example: “tiling 20 m²” or “what do you need for a quote?”.',
			greeting: 'Hello! I can help with renovation prices, the order of work and preparing a quote. Try “bathroom tiling 20 m²”, “apartment painting” or “what do you need for a quote?”.',
			thanks: 'You are welcome. A description of the work, dimensions and photos help us give a more accurate estimate.',
			contact: 'For a detailed quote, please send photos, dimensions, the address or area and a list of the work you need.',
			contactLink: 'Open the quote request form', quote: 'Photos and dimensions help us provide a realistic quote.',
			information: 'A useful enquiry includes: 1) photos of the current condition, 2) approximate dimensions, 3) the address or area, 4) your desired result, 5) whether you already have materials or need recommendations.',
			region: 'We mainly work in Tallinn and Harjumaa. For a property elsewhere, please send its location so we can check whether it fits our schedule.',
			materials: 'Some price list entries show both labour only and a price with standard materials. The final material cost depends on the product, substrate and scope. For tiling, tile size, waterproofing, levelling, grout and silicone also affect the price.',
			warranty: 'Quality depends on correct preparation and materials. The quote can specify the work, materials and warranty terms for your property.',
			time: 'Duration depends on the scope and drying times. A small painting job may take 1–3 days, while a full bathroom renovation often takes several weeks. Photos and dimensions help us give a realistic schedule.',
			pricePrompt: 'I can calculate an initial estimate from the price list. Enter the work and quantity, for example “tiling 20 m²”, “parquet 35 m²”, “toilet installation 1 item” or “facade painting 80 m²”.',
			fallback: 'I can help with renovation prices and questions about tiling, painting, drywall, parquet, demolition, plumbing, facades and bathrooms. Enter the work and, if possible, the quantity for an initial estimate.',
			price: 'Indicative price: from {price} / {unit}.', materialPrice: 'With standard materials: from {price} / {unit}.',
			estimate: 'Approximate labour cost for {amount} {unit}: <strong>from {price}</strong>.', materialEstimate: 'Approximate cost with standard materials: <strong>from {price}</strong>.',
			amountPrompt: 'Enter a quantity, such as “25 m²”, for a quick initial estimate.', multiple: 'I found several items in the price list:', from: 'from',
			combined: 'If all work priced per m² covers about {amount} m², the indicative labour cost is <strong>from {price}</strong>.',
			differentAmounts: 'For different quantities, enter them separately, for example: “filling 40 m², painting 40 m², parquet 25 m²”.',
			package: 'Example package: from {price}. Usually includes: {items}. The final price depends on condition, materials and scope.'
		},
		ru: {
			title: 'Узнать стоимость', open: 'Открыть калькулятор стоимости', close: 'Закрыть калькулятор стоимости', send: 'Отправить', client: 'Клиент',
			placeholder: 'Например: плитка 20 м²',
			welcome: 'Здравствуйте! Я помощник Renoveeri Kodu. Задайте вопрос о ценах, порядке работ или подготовке сметы. Например: «плитка 20 м²» или «что нужно для расчёта стоимости?».',
			greeting: 'Здравствуйте! Помогу с ценами на ремонт, порядком работ и подготовкой сметы. Напишите, например: «укладка плитки 20 м²», «покраска квартиры» или «что нужно для расчёта стоимости?».',
			thanks: 'Пожалуйста. Описание работ, размеры и фотографии помогут уточнить оценку.',
			contact: 'Для точного предложения пришлите фотографии, размеры, адрес или район и перечень необходимых работ.',
			contactLink: 'Открыть форму запроса стоимости', quote: 'Фотографии и размеры помогают подготовить реалистичное предложение.',
			information: 'Полезный запрос содержит: 1) фотографии текущего состояния, 2) примерные размеры, 3) адрес или район, 4) желаемый результат, 5) информацию о том, есть ли материалы или нужны рекомендации.',
			region: 'Мы работаем преимущественно в Таллинне и Харьюмаа. Если объект находится в другом месте, укажите адрес, чтобы мы могли проверить возможность выполнения работ.',
			materials: 'Для части работ в прайс-листе отдельно указана стоимость работ и стоимость со стандартными материалами. Итоговые расходы зависят от материала, основания и объёма. При укладке плитки на цену также влияют формат плитки, гидроизоляция, выравнивание, затирка и силикон.',
			warranty: 'Качество зависит от правильной подготовки и материалов. В предложении уточняются состав работ, материалы и гарантийные условия для конкретного объекта.',
			time: 'Срок зависит от объёма и времени высыхания. Небольшие малярные работы могут занять 1–3 дня, а полный ремонт ванной — несколько недель. По фотографиям и размерам мы сможем предложить более точный график.',
			pricePrompt: 'Могу рассчитать предварительную стоимость по прайс-листу. Укажите работу и количество, например: «плитка 20 м²», «паркет 35 м²», «установка унитаза 1 шт.» или «покраска фасада 80 м²».',
			fallback: 'Помогу с ценами и вопросами о плитке, покраске, гипсокартоне, паркете, демонтаже, сантехнике, фасадах и ванной. Укажите вид работ и по возможности количество, чтобы получить предварительную оценку.',
			price: 'Ориентировочная цена: от {price} / {unit}.', materialPrice: 'Со стандартными материалами: от {price} / {unit}.',
			estimate: 'Примерная стоимость работ для {amount} {unit}: <strong>от {price}</strong>.', materialEstimate: 'Примерно со стандартными материалами: <strong>от {price}</strong>.',
			amountPrompt: 'Укажите количество, например «25 м²», для предварительного расчёта.', multiple: 'Найдено несколько работ в прайс-листе:', from: 'от',
			combined: 'Если объём всех работ с ценой за м² составляет около {amount} м², ориентировочная стоимость работ — <strong>от {price}</strong>.',
			differentAmounts: 'Если объёмы различаются, укажите их отдельно, например: «шпаклёвка 40 м², покраска 40 м², паркет 25 м²».',
			package: 'Пример пакета: от {price}. Обычно включает: {items}. Точная цена зависит от состояния, материалов и объёма работ.'
		}
	};
	var messages = messagesByLanguage[language];
	function t(key, values) {
		return messages[key].replace(/\{(\w+)\}/g, function (match, name) {
			return values && Object.prototype.hasOwnProperty.call(values, name) ? String(values[name]) : match;
		});
	}
	function displayUnit(unit) {
		var units = { et: {m2: 'm²', jm: 'jm', tk: 'tk', komplekt: 'komplekt'}, en: {m2: 'm²', jm: 'linear m', tk: 'item', komplekt: 'set'}, ru: {m2: 'м²', jm: 'пог. м', tk: 'шт.', komplekt: 'комплект'} };
		return units[language][unit] || unit;
	}

	function normalize(text) {
		return String(text || '')
			.toLowerCase()
			.replace(/[õö]/g, 'o')
			.replace(/[ä]/g, 'a')
			.replace(/[ü]/g, 'u')
			.replace(/[š]/g, 's')
			.replace(/[ž]/g, 'z')
			.replace(/ё/g, 'е');
	}

	function escapeHtml(text) {
		return String(text).replace(/[&<>"]/g, function(char) {
			return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[char];
		});
	}

	function formatMoney(value) {
		return new Intl.NumberFormat(language === 'et' ? 'et-EE' : language === 'ru' ? 'ru-RU' : 'en-GB', {maximumFractionDigits: 2}).format(value) + ' €';
	}

	function findAmount(text) {
		var normalized = normalize(text);
		var match = normalized.match(/(\d+(?:[.,]\d+)?)\s*(?:m2|m²|м2|м²|sqm|square metres?|square meters?|ruut|jm|jooks|linear m|running m|пог\.?\s*м|кв\.?\s*м|tk|tukk|items?|pcs?|шт|komplekt|sets?|комплект)/) || normalized.match(/(\d+(?:[.,]\d+)?)\s*$/);
		var amount = match ? parseFloat(match[1].replace(',', '.')) : null;
		return amount > 0 && Number.isFinite(amount) ? amount : null;
	}

	function findServices(text) {
		var normalized = normalize(text);
		var matches = priceList.filter(function(service) {
			return service.keywords.some(function(keyword) {
				return normalized.indexOf(normalize(keyword)) !== -1;
			});
		});
		// Prefer a specific job over the general term contained in its name.
		var specificPairs = [[8, 7], [9, 7], [8, 12], [15, 14], [20, 3], [20, 2], [24, 23]];
		specificPairs.forEach(function(pair) {
			if (matches.indexOf(priceList[pair[0]]) !== -1) matches = matches.filter(function(service) { return service !== priceList[pair[1]]; });
		});
		return matches;
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
		var contact = window.RenoveeriI18n ? window.RenoveeriI18n.route('contact') : ({et: '/kontakt', en: '/en/contact', ru: '/ru/kontakty'})[language];
		return t('contact') + ' <a href="' + escapeHtml(contact) + '#vorm">' + t('contactLink') + '</a>.';
	}

	function serviceResponse(service, amount) {
		var unit = displayUnit(service.unit);
		var html = '<strong>' + escapeHtml(service.name) + '</strong><br>';
		html += t('price', {price: formatMoney(service.price), unit: unit});
		if (service.materialPrice) {
			html += '<br>' + t('materialPrice', {price: formatMoney(service.materialPrice), unit: unit});
		}
		if (amount) {
			html += '<br>' + t('estimate', {amount: amount, unit: unit, price: formatMoney(amount * service.price)});
			if (service.materialPrice) {
				html += '<br>' + t('materialEstimate', {price: formatMoney(amount * service.materialPrice)});
			}
		} else {
			html += '<br>' + t('amountPrompt');
		}
		html += '<br>' + escapeHtml(service.note);
		html += '<br><br>' + contactPrompt();
		return html;
	}

	function combinedResponse(services, amount) {
		var total = 0;
		var html = '<strong>' + t('multiple') + '</strong><br>';
		services.slice(0, 5).forEach(function(service) {
			html += '- ' + escapeHtml(service.name) + ': ' + t('from') + ' ' + formatMoney(service.price) + ' / ' + displayUnit(service.unit) + '<br>';
			if (amount && service.unit === 'm2') total += amount * service.price;
		});
		if (amount && total) {
			html += '<br>' + t('combined', {amount: amount, price: formatMoney(total)});
		}
		html += '<br><br>' + t('differentAmounts') + ' ' + contactPrompt();
		return html;
	}

	function answer(text, state) {
		var amount = findAmount(text);
		var services = findServices(text);
		var pack = findPackage(text);

		if (services[0]) state.pendingService = services[0];
		if (amount) state.pendingAmount = amount;

		if (hasAny(text, ['tere', 'hei', 'jou', 'hello', 'hi!', 'привет', 'здравствуйте', 'добрый день'])) {
			return t('greeting');
		}

		if (hasAny(text, ['aitah', 'aitäh', 'tänud', 'tanud', 'thank', 'спасибо', 'благодар'])) {
			return t('thanks') + ' ' + contactPrompt();
		}

		if (hasAny(text, ['kontakt', 'telefon', 'email', 'meil', 'ühendus', 'uhendus', 'pakkumine', 'pakkumist', 'päring', 'paring', 'contact', 'phone', 'quote', 'enquiry', 'контакт', 'телефон', 'почта', 'связаться', 'предложение', 'смет', 'расчёт стоимости', 'расчет стоимости'])) {
			return contactPrompt() + '<br>' + t('quote');
		}

		if (hasAny(text, ['mida vaja', 'mis vaja', 'mida saata', 'fotod', 'mõõdud', 'moodud', 'what do you need', 'what to send', 'photos', 'dimensions', 'что нужно', 'что отправить', 'фото', 'размер'])) {
			return t('information') + ' ' + contactPrompt();
		}

		if (!services.length && hasAny(text, ['piirkond', 'kus teete', 'tallinn', 'harjumaa', 'where do you work', 'service area', 'location', 'где работаете', 'район', 'таллин', 'харью'])) {
			return t('region');
		}

		if (!services.length && hasAny(text, ['materjal', 'materials', 'material', 'материал'])) {
			return t('materials');
		}

		if (hasAny(text, ['garantii', 'garantee', 'vastutus', 'warranty', 'guarantee', 'гаранти', 'ответственност'])) {
			return t('warranty');
		}

		if (hasAny(text, ['kaua', 'aeg', 'kestab', 'millal', 'how long', 'duration', 'when', 'срок', 'сколько времени', 'как долго', 'когда'])) {
			return t('time');
		}

		if (pack && !services.length) {
			return '<strong>' + escapeHtml(pack.title) + '</strong><br>' + t('package', {price: formatMoney(pack.price), items: escapeHtml(pack.text)}) + '<br><br>' + contactPrompt();
		}

		if (services.length > 1) return combinedResponse(services, amount || state.pendingAmount);
		if (services.length === 1) return serviceResponse(services[0], amount || state.pendingAmount);
		if (state.pendingService && amount) return serviceResponse(state.pendingService, amount);

		if (hasAny(text, ['hind', 'maksab', 'palju', 'hinnakiri', 'euro', 'price', 'cost', 'how much', 'цен', 'стоит', 'стоимость', 'евро'])) {
			return t('pricePrompt');
		}

		return t('fallback') + ' ' + contactPrompt();
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
		bubble.setAttribute('aria-label', t('open'));
		bubble.setAttribute('title', t('open'));
		close.setAttribute('aria-label', t('close'));
		input.placeholder = t('placeholder');
		input.setAttribute('aria-label', t('placeholder'));
		sendBtn.textContent = t('send');
		var header = document.getElementById('ai-header');
		if (header) {
			Array.from(header.childNodes).forEach(function(node) { if (node.nodeType === 3) node.remove(); });
			header.insertBefore(document.createTextNode(t('title') + ' '), header.firstChild);
		}
		messages.innerHTML = '<p><b>AI:</b> ' + t('welcome') + '</p>';

		function openChat(event) {
			if (event) event.preventDefault();
			chat.style.display = 'flex';
			bubble.setAttribute('aria-expanded', 'true');
			bubble.style.display = 'none';
			input.focus();
		}

		function closeChat(event) {
			if (event) event.preventDefault();
			chat.style.display = 'none';
			bubble.setAttribute('aria-expanded', 'false');
			bubble.style.display = 'flex';
			bubble.focus();
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
			addMessage(t('client'), escapeHtml(text));
			input.value = '';
			window.setTimeout(function() {
				addMessage('AI', answer(text, state));
			}, 180);
		}

		bubble.onclick = openChat;
		close.onclick = closeChat;
		chat.addEventListener('keydown', function(event) {
			if (event.key === 'Escape') closeChat(event);
		});
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
