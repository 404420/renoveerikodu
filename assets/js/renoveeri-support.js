(function (global) {
  'use strict';

  // Reviewed advice, not a live booking system. Prices are supplied by the shared catalogue.
  var copy = {
    et: {
      title: 'Renoveeri Kodu abiline', agent: 'Abiline', subtitle: 'Tööde nõu ja esmane hinnang', open: 'Küsi tööde kohta', close: 'Sulge vestlus', send: 'Saada', reset: 'Alusta uut vestlust',
      placeholder: 'Kirjelda tööd või küsi nõu…', welcome: 'Tere! Aitan sul töömahtu täpsustada, hinnakirja järgi eelarvet hinnata ja pakkumise jaoks vajalikku infot koguda. Millist tööd plaanid?',
      disclosure: 'Automaatne abiline. Lõpliku pakkumise ja tööaja kinnitab meie meeskond.', privacy: 'Vestlus püsib sellel lehel. Meeskonnale jõuab päring alles pärast kontaktivormi saatmist.',
      quote: 'Koosta hinnapäring', phone: 'Helista meistrile', pricing: 'Vaata hinnakirja', transfer: 'Lisa vestluse kokkuvõte päringusse', transferred: 'Vestluse kokkuvõte on lisatud. Vaata kirjeldus üle, lisa kontaktandmed ja fotod ning saada päring.',
      chips: ['Soovin hinnapakkumist', 'Parketi paigaldus', 'Vannitoa remont', 'Mis on hinnas sees?'],
      clarify: 'Millist tööd mõtled? Kirjuta näiteks „parketi paigaldus 25 m²”.', unit: 'Selle töö ühik on {unit}. Palun täpsusta kogus selles ühikus; teises ühikus antud kogust ma automaatselt ei teisenda.',
      multiple: 'Iga töö jaoks on vaja eraldi kogust. Näiteks „pahteldus 40 m², värvimine 30 m², liistud 12 jm”.',
      missing: 'Kui suur on selle töö maht ({unit})? Kui mõõte veel ei tea, saab alustada fotodest ja töö kirjeldusest.',
      disclaimer: 'See on hinnakirja alghind, mitte siduv pakkumine. Aluspinna ettevalmistus, eemaldus, materjalid, transport ja jäätmekäitlus täpsustatakse eraldi.',
      total: 'Kirjeldatud tööde tööraha orientiir kokku: <strong>al {price}</strong>.', partial: 'Kõigi tööde kogused ei ole teada; terviku maksumust veel ei liida.',
      noQuantity: 'Kogus peab olema positiivne ning ühik selge. Kirjuta näiteks „25 m²” või „12 jm”.',
      summary: 'Vestluse kokkuvõte (kliendi kirjeldus, hinnang ei ole pakkumine):', materialsFollow: 'Kas materjalid on hinnas?', locationFollow: 'Kus objekt asub?', timelineFollow: 'Millal saab alustada?', prepFollow: 'Kuidas tööks valmistuda?',
      locationAck: 'Panin asukoha töö kirjeldusse. Töötame peamiselt Tallinnas ja Harjumaal; teiste piirkondade võimaluse kinnitab meeskond. Milliseid töid ja ligikaudset mahtu soovid?',
      timingAck: 'Panin soovitud aja töö kirjeldusse. Vaba tööaja kinnitab meeskond. Kas soovid lisada veel töömahtu või koostada hinnapäringu?',
      unknown: 'Selle küsimuse kohta mul kinnitatud vastust veel pole. Kirjelda palun soovitud tööd või saada küsimus meistrile; ma ei kinnita oletuse põhjal hinda ega teostusvõimalust.',
      estimateQuestion: 'Täpse pakkumise jaoks on vaja töö kirjeldust, asukohta, mõõte ja fotosid. Alustame töö liigist: mida soovid teha?',
      areaWarning: 'Korteri või toa põrandapind ei ole sama mis värvitavate seinte ja lagede pindala. Hinnangu jaoks on vaja viimistletava pinna mõõte.',
      topics: {
        region: 'Töötame peamiselt Tallinnas ja Harjumaal. Mujal asuva objekti puhul kontrollib meeskond võimalust eraldi. Mis linnas või vallas objekt asub?',
        materials: 'Hinnakirjas on materjalid kaasas ainult siis, kui näidatud on eraldi „koos materjaliga” hind. Selle sisu täpsustame pakkumises; näiteks plaatide või parketi ostuhinda ei saa automaatselt tööraha sisse arvestada. Kas materjalid on sul olemas või soovid abi valikul ja hankimisel?',
        included: 'Töö alghind ei ole automaatselt kogu remondi hind. Pakkumises eristame tööd, materjalid, ettevalmistuse, eemaldamise, transpordi ja prahi äraveo. Millise töö hinna sisu soovid täpsustada?',
        warranty: 'Veebilehel on tehtud ehitus- ja viimistlustöödele 3-aastane garantii. Konkreetne töömaht, garantiitingimused ja üleandmine fikseeritakse kokkuleppes. Olemasoleva töö mure korral kirjelda probleemi ja lisa kontaktivormi fotod.',
        availability: 'Vaba tööaja ja objekti ülevaatuse kinnitab meeskond. Kirjuta soovitud algus, asukoht ja töömaht; saan need hinnapäringusse kaasa võtta.',
        duration: 'Kestus sõltub töömahust, aluspinnast, kuivamisaegadest ja materjalidest. Vannitoa töödel tuleb arvestada mitme etapiga; tööde täpne ajakava selgub ülevaatuse ja pakkumise käigus. Milline töö ja kui suur pind on plaanis?',
        payment: 'Maksegraafik, ettemaks, arve ja võimalik käibemaks fikseeritakse konkreetses pakkumises või lepingus. Ma ei kinnita maksetingimusi ilma meeskonna kokkuleppeta. Küsi neid päringus koos töö kirjeldusega.',
        preparation: 'Enne tööd arutame läbi ligipääsu, mööbli, olemasolevate pindade kaitse, vee ja elektri ning jäätmete äraandmise. Ära eemalda ise konstruktsioone enne lahenduse kinnitamist. Millises ruumis ja millist tööd plaanid?',
        sequence: 'Tavaliselt alustatakse ülevaatusest ja mõõdistusest, seejärel eemaldus ning vajalikud tehnilised tööd, aluspinna ettevalmistus, viimistlus ja lõppkontroll. Märgruumis tuleb enne plaatimist lahendada aluspind, kalded ja hüdroisolatsioon. Täpne järjekord sõltub objektist.',
        bathroom: 'Vannitoa remondi puhul täpsustame eemaldust, vee- ja elektrilahendust, aluspinda, kaldeid, hüdroisolatsiooni, plaatimist ning sanitaartehnikat. Põrandapind ei võrdu seinte ja põranda plaatimispinnaga. Kui suur on vannituba ja kas soovid tervikremonti või ainult plaatimist?',
        flooring: 'Parketi või laminaadi valikul on olulised aluspinna tasasus ja niiskus, põrandaküte, paigaldusviis ning tootja nõuded. Vana katte eemaldus, tasandus ja liistud täpsustatakse eraldi. Kas plaanid laudparketti, laminaati või kalasabaparketti?',
        heating: 'Põrandakütte korral peab kate, alusmaterjal või liim sobima konkreetse küttesüsteemiga. Enne paigaldust kontrollitakse aluspinna niiskust ja tootja nõudeid. Mis põrandakatet ja millist küttesüsteemi kasutad?',
        waste: 'Lammutus, prahi kokkukogumine, äravedu ja jäätmekäitlus täpsustatakse töömahus eraldi. Vajame infot ligipääsu, korruse, lifti ja jäätmete liigi kohta. Kas eemaldada tuleb vana kate või ka muid materjale?',
        damp: 'Niiskuse või hallituse puhul tuleb enne viimistlust leida põhjus ja hinnata kahjustuse ulatust. Ainult ülevärvimine ei lahenda põhjust. Saada fotod ja kirjelda, millal probleem tekkis; sobiva lahenduse kinnitab spetsialist pärast ülevaatust.',
        structural: 'Kandva seina avamist või eemaldamist ei saa kinnitada vestluse või foto järgi. Vajalik on konstruktsiooni kontroll ja vajaduse korral projekt ning kooskõlastused. Ära alusta lammutust enne pädeva spetsialisti hinnangut.',
        emergency: 'See vestlus ei ole avarii väljakutsekanal. Vahetu ohu korral eemaldu ohualast ja kutsu vajalik avariiabi. Ära puuduta märgi elektriseadmeid ega tee pingestatud elektritöid. Meie meeskonna saad kätte telefonil +372 5551 5783; väljakutse võimalus tuleb eraldi kinnitada.',
        photos: 'Fotod ja failid saad lisada kontaktivormis. Hea päring sisaldab üldvaadet, probleemkoha lähivõtet, mõõte, asukohta ja soovitud tulemust. Vestlus pilte ei analüüsi ega saada neid meeskonnale.',
        human: 'Meistriga saad ühendust telefonil +372 5551 5783 või e-postil info@renoveerikodu.ee. Põhjalikuma kirjelduse ja fotod saad saata kontaktivormis. Vestlus ei broneeri aega ega saada ise päringut.',
        services: 'Aitame siseviimistluse, plaatimise, põrandate, kipsi, lammutuse, sanitaarpaigalduse, fassaadi, katuse, vundamendi, betooni, tellingute, seinapaneelide ja LED-lahendustega. Erilahenduste teostatavuse ning hinna kinnitab meeskond. Milline töö on plaanis?',
        repair: 'Kahjustuse paranduse võimalus sõltub materjalist ja kahjustuse ulatusest. Parketi täkke, kriimu või muu viimistlusvea kohta saada üldfoto ning lähivõte. Ülevaatuse järel selgub, kas piisab kohalikust parandusest või on vaja põhjalikumat tööd.',
        unsupported: 'Selle töö jaoks puudub ühikuhind roboti hinnakirjas. Saada töö kirjeldus, mõõdud ja fotod, et meeskond saaks kontrollida lahendust ja koostada objekti järgi pakkumise.',
        identity: 'Olen Renoveeri Kodu automaatne virtuaalne abiline. Aitan hinnakirja ja tööde ettevalmistusega; lõpliku pakkumise, broneeringu ja tehnilise lahenduse kinnitab inimene.'
      }
    },
    en: {
      title: 'Renoveeri Kodu assistant', agent: 'Assistant', subtitle: 'Renovation advice and estimates', open: 'Ask about your project', close: 'Close conversation', send: 'Send', reset: 'Start a new conversation', placeholder: 'Describe your project or ask a question…',
      welcome: 'Hello! I can help clarify the scope of work, estimate costs from our price list and prepare a quote request. What are you planning?', disclosure: 'Automated assistant. Our team confirms final quotes and availability.', privacy: 'This conversation stays on this page. Our team receives your enquiry only after you submit the contact form.',
      quote: 'Prepare a quote request', phone: 'Call our team', pricing: 'View prices', transfer: 'Include conversation in my enquiry', transferred: 'The conversation summary has been added. Review it, add your contact details and photos, then submit the enquiry.', chips: ['I need a quote', 'Parquet installation', 'Bathroom renovation', 'What is included?'],
      clarify: 'Which work do you mean? For example, “parquet installation 25 m²”.', unit: 'This work is priced per {unit}. Please give a quantity in that unit; I cannot automatically convert other units.', multiple: 'Each job needs its own quantity, for example “filling 40 m², painting 30 m², skirting 12 linear m”.', missing: 'What is the quantity for this work ({unit})? If you do not know yet, start with photos and a description.',
      disclaimer: 'This is a starting price from the price list, not a binding quote. Preparation, removal, materials, transport and waste disposal must be specified separately.', total: 'Indicative labour total for the described work: <strong>from {price}</strong>.', partial: 'Some quantities are missing; I cannot total the complete scope yet.', noQuantity: 'Please give a positive quantity and a clear unit, such as “25 m²” or “12 linear m”.',
      summary: 'Conversation summary (customer description, estimate is not a quote):', materialsFollow: 'Are materials included?', locationFollow: 'Where do you work?', timelineFollow: 'When can you start?', prepFollow: 'How should I prepare?', locationAck: 'I added the location to your project description. We mainly work in Tallinn and Harjumaa; our team confirms other locations. What work and quantities do you need?', timingAck: 'I added your preferred date to the project description. Our team confirms availability. Would you like to add quantities or prepare an enquiry?', unknown: 'I do not have a confirmed answer to that question. Please describe the work or send your question to our team; I cannot confirm a price or feasibility by guessing.', estimateQuestion: 'A detailed quote needs the work description, location, measurements and photos. What work would you like done?', areaWarning: 'The floor area of a room or apartment is not the area of walls and ceilings to paint. Please give the actual surface area.',
      topics: {
        region: 'We mainly work in Tallinn and Harjumaa. Our team checks other locations individually. In which town or municipality is your property?', materials: 'Materials are included only where the price list explicitly shows a price with materials. The exact contents are specified in the quote; tiles or flooring are not automatically included in labour prices. Do you already have materials or need help choosing them?', included: 'A starting labour price is not the total renovation cost. The quote specifies work, materials, preparation, removal, transport and waste disposal separately. Which work would you like to clarify?',
        warranty: 'Our website states a 3-year warranty on completed building and finishing work. Scope, warranty terms and handover are agreed for your project. For a problem with completed work, describe it and attach photos to the contact form.', availability: 'Our team confirms available dates and site visits. Please tell us your preferred start, location and scope so these can accompany your enquiry.', duration: 'Duration depends on scope, substrate, drying times and materials. Bathroom renovation has several stages. The schedule is confirmed after assessment. What work and area are involved?', payment: 'Payment schedule, deposit, invoicing and any VAT are specified in the individual quote or contract. I cannot confirm payment terms without the team’s agreement.', preparation: 'Before work, we discuss access, furniture, protection, water, electricity and waste handling. Do not remove structural elements before the solution is approved. What room and work are involved?', sequence: 'Typically: inspection and measurements, removal and technical work, substrate preparation, finishing and final checks. Wet rooms need suitable substrates, falls and waterproofing before tiling. The exact sequence depends on the property.',
        bathroom: 'For bathrooms we clarify removal, plumbing and electrical requirements, substrates, falls, waterproofing, tiling and fixtures. Floor area is not the total tiling area. How large is the bathroom, and do you need a full renovation or just tiling?', flooring: 'Flooring choices depend on substrate flatness and moisture, underfloor heating, installation method and manufacturer requirements. Removal, levelling and skirting are specified separately. Are you considering engineered wood, laminate or herringbone?', heating: 'Flooring, underlay or adhesive must suit the heating system. Substrate moisture and manufacturer requirements need checking before installation. What flooring and heating system do you have?', waste: 'Removal, collection, transport and waste disposal are specified separately. Access, floor level, lift and waste type matter. What needs removing?', damp: 'Find the cause of damp or mould and assess the damage before refinishing. Painting over it does not resolve the cause. Send photos and explain when it appeared; a specialist must assess the solution.', structural: 'Structural wall removal cannot be approved in a chat or from a photo. A qualified assessment and, where necessary, a design and approvals are required. Do not begin demolition before that assessment.', emergency: 'This chat is not an emergency dispatch service. In immediate danger, leave the unsafe area and contact the appropriate emergency service. Do not touch wet electrical equipment or work on live wiring. Call +372 5551 5783 to check whether our team can help.', photos: 'Attach photos and files in the contact form: an overview, close-ups, dimensions, location and desired result. This chat does not analyse images or send them to the team.', human: 'Call +372 5551 5783 or email info@renoveerikodu.ee. Send a detailed description and photos through the contact form. This chat does not book appointments or automatically send an enquiry.', services: 'We offer finishing, tiling, flooring, drywall, demolition, fixture installation, facades, roofing, foundations, concrete, scaffolding, wall panels and LED solutions. Our team confirms feasibility and prices for bespoke work. What are you planning?', repair: 'Repair options depend on the material and damage. Send an overview and close-up of the floor dent, scratch or finishing defect. Assessment determines whether a local repair is enough.', unsupported: 'There is no unit price for this work in the assistant’s price list. Send a description, dimensions and photos for an assessment and project-specific quote.', identity: 'I am the automated Renoveeri Kodu virtual assistant. I help with prices and preparation; a person confirms final quotes, bookings and technical solutions.'
      }
    },
    ru: {
      title: 'Помощник Renoveeri Kodu', agent: 'Помощник', subtitle: 'Советы по ремонту и оценка стоимости', open: 'Спросить о ремонте', close: 'Закрыть разговор', send: 'Отправить', reset: 'Начать новый разговор', placeholder: 'Опишите работу или задайте вопрос…', welcome: 'Здравствуйте! Помогу уточнить объём работ, оценить бюджет по прайс-листу и подготовить запрос предложения. Какую работу планируете?', disclosure: 'Автоматический помощник. Окончательную цену и сроки подтверждает наша команда.', privacy: 'Разговор остаётся на этой странице. Команда получит запрос только после отправки контактной формы.',
      quote: 'Подготовить запрос', phone: 'Позвонить мастеру', pricing: 'Посмотреть цены', transfer: 'Добавить разговор к запросу', transferred: 'Краткое содержание разговора добавлено. Проверьте описание, добавьте контакты и фотографии, затем отправьте запрос.', chips: ['Нужно предложение', 'Укладка паркета', 'Ремонт ванной', 'Что входит в цену?'], clarify: 'Какую работу вы имеете в виду? Например: «укладка паркета 25 м²».', unit: 'Единица этой работы: {unit}. Укажите объём в этой единице; другие единицы автоматически не пересчитываются.', multiple: 'Для каждой работы нужен отдельный объём, например: «шпаклёвка 40 м², покраска 30 м², плинтус 12 пог. м».', missing: 'Каков объём этой работы ({unit})? Если размеров пока нет, можно начать с фотографий и описания.', disclaimer: 'Это начальная цена прайс-листа, а не окончательное предложение. Подготовку, демонтаж, материалы, транспорт и вывоз мусора согласовываем отдельно.', total: 'Ориентировочная стоимость описанных работ: <strong>от {price}</strong>.', partial: 'Не все объёмы известны; общую стоимость пока не суммирую.', noQuantity: 'Укажите положительный объём и единицу, например «25 м²» или «12 пог. м».', summary: 'Краткое содержание разговора (описание клиента, оценка не является предложением):', materialsFollow: 'Материалы включены?', locationFollow: 'Где вы работаете?', timelineFollow: 'Когда можно начать?', prepFollow: 'Как подготовиться?', locationAck: 'Добавил расположение в описание. Работаем преимущественно в Таллинне и Харьюмаа; другие районы подтверждает команда. Какие работы и объёмы нужны?', timingAck: 'Добавил желаемый срок в описание. Свободное время подтверждает команда. Уточним объём или подготовим запрос?', unknown: 'На этот вопрос у меня пока нет подтверждённого ответа. Опишите работу или передайте вопрос мастеру; цену и возможность выполнения нельзя подтверждать предположением.', estimateQuestion: 'Для точного предложения нужны описание работ, расположение, размеры и фото. Какую работу нужно выполнить?', areaWarning: 'Площадь пола квартиры или комнаты не равна площади стен и потолков для покраски. Нужны размеры отделываемой поверхности.',
      topics: {
        region: 'Работаем преимущественно в Таллинне и Харьюмаа. Возможность работ в других местах команда проверяет отдельно. В каком городе или волости объект?', materials: 'Материалы включены только там, где в прайс-листе есть отдельная цена с материалами. Состав уточняем в предложении; плитка и напольное покрытие не включаются автоматически в стоимость работ. Материалы уже есть или нужна помощь с выбором?', included: 'Начальная цена работы не равна полной стоимости ремонта. Работы, материалы, подготовку, демонтаж, транспорт и мусор уточняем отдельно. Какую работу хотите уточнить?', warranty: 'На сайте указана гарантия 3 года на выполненные строительные и отделочные работы. Объём, условия и сдача согласовываются для объекта. Если возникла проблема, опишите её и добавьте фото в контактную форму.', availability: 'Свободные даты и осмотр подтверждает команда. Укажите желаемое начало, расположение и объём для запроса.', duration: 'Срок зависит от объёма, основания, высыхания и материалов. Ремонт ванной состоит из нескольких этапов. График уточняем после осмотра. Какие работы и площадь планируются?', payment: 'График оплаты, предоплата, счета и возможный НДС фиксируются в конкретном предложении или договоре. Без согласования команды я их не подтверждаю.', preparation: 'Обсуждаем доступ, мебель, защиту поверхностей, воду, электричество и мусор. Не разбирайте конструкции до согласования решения. В какой комнате нужны работы?', sequence: 'Обычно: осмотр и замеры, демонтаж и технические работы, подготовка основания, отделка и проверка. В мокрых помещениях до плитки решают основание, уклоны и гидроизоляцию. Порядок зависит от объекта.', bathroom: 'Для ванной уточняем демонтаж, трубы и электрику, основание, уклоны, гидроизоляцию, плитку и сантехнику. Площадь пола не равна общей площади плитки. Каков размер ванной: нужен полный ремонт или только плитка?', flooring: 'Для паркета и ламината важны ровность и влажность основания, тёплый пол, способ монтажа и требования производителя. Демонтаж, выравнивание и плинтусы уточняем отдельно. Планируете паркетную доску, ламинат или ёлочку?', heating: 'Покрытие, подложка или клей должны подходить для системы отопления. Перед укладкой проверяют влажность основания и требования производителя. Какое покрытие и отопление используются?', waste: 'Демонтаж, сбор, транспорт и утилизация согласовываются отдельно. Важны доступ, этаж, лифт и вид мусора. Что нужно удалить?', damp: 'До отделки нужно найти причину сырости или плесени и оценить повреждение. Покраска причину не устранит. Отправьте фото и время появления; решение подтвердит специалист после осмотра.', structural: 'Снос несущей стены нельзя подтвердить в разговоре или по фото. Нужны проверка специалистом и при необходимости проект и согласования. Не начинайте демонтаж до проверки.', emergency: 'Этот разговор не является каналом аварийного вызова. При непосредственной опасности покиньте опасную зону и вызовите соответствующую аварийную службу. Не трогайте мокрые электроприборы и проводку под напряжением. Возможность помощи команды уточните по +372 5551 5783.', photos: 'Фотографии и файлы добавляются в контактную форму: общий вид, крупный план, размеры, расположение и желаемый результат. Этот разговор не анализирует и не отправляет фотографии.', human: 'Свяжитесь по +372 5551 5783 или info@renoveerikodu.ee. Подробности и фотографии отправьте через контактную форму. Разговор сам не бронирует время и не отправляет запрос.', services: 'Выполняем отделку, плитку, полы, гипсокартон, демонтаж, монтаж сантехники, фасады, кровлю, фундаменты, бетон, леса, стеновые панели и LED-решения. Особые работы и цены подтверждает команда. Что планируете?', repair: 'Возможность ремонта зависит от материала и повреждения. Пришлите общий вид и крупный план вмятины, царапины или дефекта отделки. После осмотра определим, достаточно ли местного ремонта.', unsupported: 'Для этой работы нет единичной цены в прайс-листе помощника. Отправьте описание, размеры и фотографии для проверки решения и предложения по объекту.', identity: 'Я автоматический виртуальный помощник Renoveeri Kodu. Помогаю с ценами и подготовкой; окончательные предложения, бронирование и технические решения подтверждает человек.'
      }
    }
  };

  var intents = [
    ['emergency', /(?:avarii|aktiivne leke|vesi (?:jookseb|uputab)|suits|sadele|pinge all|emergency|flooding|live wir|smoke|искрит|авари|затоп|дым|под напряжением)/],
    ['structural', /(?:kand(?:e)?v|несущ|load.bearing|bearing wall|lammuta.*sein|sein.*(?:maha|eemald)|remove.*wall)/],
    ['damp', /(?:hallitus|niiskuskahjust|niiske sein|mould|mold|damp|плесен|сырост)/],
    ['identity', /(?:oled (?:robot|inimene|ai)|kes sa oled|are you (?:human|a bot|ai)|who are you|ты (?:робот|человек)|кто ты)/],
    ['flooring', /(?:(?:mis vahe|kumb|milline).*(?:parket|lamina)|kas.*(?:parket|lamina).*(?:sobib|liimida|ujuvalt)|(?:difference|which|suitable).*(?:floor|parquet|laminate)|(?:разница|что лучше|подходит).*(?:паркет|ламинат)|(?:паркет|ламинат).*подходит)/],
    ['sequence', /(?:kas.*hudro.*vaja|miks.*hudro|kas.*krunti|waterproof.*(?:need|required)|need.*prim|нужн.*(?:гидро|грунт))/],
    ['warranty', /(?:garant|warranty|guarantee|гаранти|reklamatsioon|complaint|претензи)/],
    ['payment', /(?:ettemaks|maksm|makse|arve|sularaha|kaibemaks|km\b|ja[e]?relmaks|payment|deposit|invoice|vat\b|предоплат|оплат|рассроч|ндс|счет|счёт)/],
    ['availability', /(?:vaba aeg|vabu aegu|alust|millal|ulevaatus|kohale|broneer|start|available|availability|book|site visit|начать|свободн|осмотр|когда)/],
    ['duration', /(?:kaua|kestab|kestus|ajakava|kuivab|how long|duration|drying|schedule|срок|как долго|высых)/],
    ['included', /(?:hinnas sees|hinna sisse|hind sisald|mida sisald|mis sisald|hinna sees|included|include|входит|включен|включён)/],
    ['materials', /(?:materjal|material|материал)/],
    ['photos', /(?:foto|pilt|fail|photo|picture|upload|фото|файл|снимок)/],
    ['human', /(?:helista|telefon|e.post|email|e-mail|kontakt|meistriga|inimesega|human|phone|call|contact|телефон|позвон|почта|мастером)/],
    ['region', /(?:kus (?:tootate|teete)|piirkond|kas.*(?:tartu|parnu|tallinn|harjumaa)|where.*work|service area|где.*работ|район)/],
    ['preparation', /(?:valmistu|ettevalmist|moobel|kaitsm|prepare|preparation|furniture|подготов|мебел)/],
    ['sequence', /(?:jarjekor|mis enne|etapid|sequence|order of work|stages|порядок|этап)/],
    ['heating', /(?:porandakut|underfloor|heated floor|теплыи пол|теплый пол|тёплый пол)/],
    ['waste', /(?:prahi|prugi|jaatme|araved|waste|rubbish|debris|мусор|вывоз)/],
    ['repair', /(?:kriim|take|takk|kuumvaha|parand|scratch|dent|repair|царап|вмят|поврежд)/],
    ['services', /(?:mis (?:toid|teenus)|mida teete|services|what.*(?:do|offer)|какие.*(?:работ|услуг))/],
    ['unsupported', /(?:katus|vundament|betoon|telling|led|paneel|roof|foundation|concrete|scaffold|panel|кровл|фундамент|бетон|леса|панел)/]
  ];

  function normalize(s) { return String(s || '').toLowerCase().replace(/[õö]/g, 'o').replace(/ä/g, 'a').replace(/ü/g, 'u').replace(/š/g, 's').replace(/ž/g, 'z').replace(/ё/g, 'е'); }
  function escape(s) { return String(s).replace(/[&<>"']/g, function (ch) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]; }); }
  function fill(s, data) { return s.replace(/\{(\w+)\}/g, function (_, k) { return data[k]; }); }
  function createState() { return {pending: [], last: [], rows: [], transcript: []}; }

  function createEngine(config) {
    var lang = config.language, c = copy[lang], prices = config.priceList, messages = config.messages;
    var units = {et: {m2:'m²',jm:'jm',tk:'tk',komplekt:'komplekt'},en:{m2:'m²',jm:'linear m',tk:'item',komplekt:'set'},ru:{m2:'м²',jm:'пог. м',tk:'шт.',komplekt:'комплект'}}[lang];
    function money(n) { return new Intl.NumberFormat({et:'et-EE',en:'en-GB',ru:'ru-RU'}[lang], {maximumFractionDigits:2}).format(n) + ' €'; }
    function response(key, suggestions) { return {html: c.topics[key] || c[key], suggestions: suggestions || [c.quote, c.phone]}; }
    function services(text) {
      var s = normalize(text), hits = [];
      prices.forEach(function (p, index) {
        p.keywords.forEach(function (keyword) {
          var k = normalize(keyword), start = 0, pos;
          while ((pos = s.indexOf(k, start)) !== -1) {
            // Avoid matching "tere" in "materjal" and similar substrings inside a word.
            if (pos === 0 || !/[a-zа-я]/.test(s[pos-1])) hits.push({index:index,start:pos,end:pos+k.length});
            start = pos+k.length;
          }
        });
      });
      hits = hits.filter(function (h) { return !hits.some(function (other) { return other.index !== h.index && other.start <= h.start && other.end >= h.end && other.end-other.start > h.end-h.start; }); });
      var ids = [];
      hits.sort(function (a,b) { return a.start-b.start; }).forEach(function (h) { if (ids.indexOf(h.index) === -1) ids.push(h.index); });
      // A named facade job is more specific than standalone "painting" / "priming".
      if (ids.indexOf(20) !== -1) ids = ids.filter(function (id) { return id !== 2 && id !== 3; });
      if (ids.indexOf(27) !== -1 || ids.indexOf(28) !== -1) ids = ids.filter(function (id) { return id !== 10; });
      [[8,7],[8,12],[9,7],[15,14],[24,23]].forEach(function(pair){if(ids.indexOf(pair[0])!==-1) ids=ids.filter(function(id){return id!==pair[1];});});
      return ids;
    }
    function quantities(text, bare) {
      var s = normalize(text), re = /(-?\d+(?:[.,]\d+)?)\s*(m²|m2|м²|м2|sqm|square metres?|square meters?|ruut(?:meetrit)?|jm|jooks(?:vat)?(?: meetrit)?|linear m|running m|пог\.?\s*м|кв\.?\s*м|tk|tukki|tukk|items?|pcs?|шт\.?|komplekt|sets?|комплект)/g;
      var match, result = [];
      while ((match = re.exec(s))) {
        var label = match[2], unit = /^(m|м|sqm|square|ruut|кв)/.test(label) ? 'm2' : /^(jm|jooks|linear|running|пог)/.test(label) ? 'jm' : /^(komplekt|set|комплект)/.test(label) ? 'komplekt' : 'tk';
        result.push({amount:Number(match[1].replace(',','.')), unit:unit, start:match.index, end:re.lastIndex});
      }
      if (!result.length && bare && /^\d+(?:[.,]\d+)?$/.test(s.trim())) result.push({amount:Number(s.trim().replace(',','.')),unit:null});
      return result;
    }
    function renderRows(rows, state) {
      var total=0, complete=true, html=[];
      rows.forEach(function (row) {
        var p=prices[row.id], q=row.quantity;
        var line='<strong>'+escape(p.name)+'</strong><br>'+fill(messages.price,{price:money(p.price),unit:units[p.unit]});
        if (p.materialPrice) line+='<br>'+fill(messages.materialPrice,{price:money(p.materialPrice),unit:units[p.unit]});
        if (q && q.amount > 0 && q.amount <= 100000 && (!q.unit || q.unit===p.unit)) {
          line+='<br>'+fill(messages.estimate,{amount:q.amount,unit:units[p.unit],price:money(q.amount*p.price)});
          if(p.materialPrice) line+='<br>'+fill(messages.materialEstimate,{price:money(q.amount*p.materialPrice)});
          total+=q.amount*p.price;
        } else {
          complete=false;
          line+='<br>'+(!q ? fill(c.missing,{unit:units[p.unit]}) : q.amount <= 0 || q.amount>100000 ? c.noQuantity : fill(c.unit,{unit:units[p.unit]}));
        }
        line+='<br>'+escape(p.note);
        html.push(line);
      });
      state.last=rows.map(function (r) {return r.id;});
      state.rows=rows;
      state.pending=rows.filter(function (r) {return !r.quantity || r.quantity.unit && r.quantity.unit!==prices[r.id].unit || r.quantity.amount<=0 || r.quantity.amount>100000;}).map(function(r){return r.id;});
      if(rows.length>1) html.push(complete ? fill(c.total,{price:money(total)}) : c.partial);
      html.push('<small>'+c.disclaimer+'</small>');
      return {html:html.join('<br><br>'),suggestions:[c.materialsFollow,c.prepFollow,c.quote]};
    }
    function answer(raw, state) {
      var text=String(raw||'').trim().slice(0,1500), s=normalize(text), ids=services(text), qs=quantities(text,false);
      if (!text) return response('clarify');
      state.transcript.push(text); state.transcript=state.transcript.slice(-16);
      var urgent=intents.slice(0,4).find(function (intent) {
        var candidate=intent[0]==='structural' ? s.replace(/mittekand\w*|non.load.bearing|ненесущ[а-я]*/g,'') : s;
        return intent[1].test(candidate);
      });
      if(urgent) {state.pending=[];state.last=[];state.rows=[];return response(urgent[0]);}
      if (/^(?:tere|tervist|hei|hello|hi|привет|здравствуйте)[!.\s]*$/.test(s)) return response('welcome',c.chips);
      if (/^(?:aitah|tanud|thanks|thank you|спасибо)[!.\s]*$/.test(s)) return {html:messages.thanks,suggestions:[c.quote,c.phone]};
      var matched=intents.slice(4).filter(function (intent) {return intent[1].test(s);});
      // A quote request with a work and quantity should still receive an estimate.
      var asksPrice=/(?:hind|hinnang|maksab|maksumus|price|cost|estimate|цен|стоим|стоит)/.test(s);
      if(matched.length && !(asksPrice && ids.length && !matched.some(function (i) {return ['payment','warranty','duration','availability','included'].indexOf(i[0])!==-1;}))) {
        var main=matched[0][0];
        if(main==='included' && /(?:materjal|material|материал)/.test(s)) main='materials';
        // Answer up to two explicit questions without a wall of unrelated suggestions.
        var keys=[main];
        matched.forEach(function (i) {if(keys.length<2 && keys.indexOf(i[0])===-1 && i[0]!=='unsupported' && !(main==='materials' && i[0]==='included')) keys.push(i[0]);});
        var advice=keys.map(function(k){return c.topics[k];}).join('<br><br>');
        if(main==='materials' && !ids.length) state.rows.forEach(function(row){
          var p=prices[row.id];
          if(p.materialPrice){
            advice+='<br><br><strong>'+escape(p.name)+'</strong><br>'+fill(messages.materialPrice,{price:money(p.materialPrice),unit:units[p.unit]});
            if(row.quantity && row.quantity.amount>0 && row.quantity.amount<=100000 && (!row.quantity.unit || row.quantity.unit===p.unit)) advice+='<br>'+fill(messages.materialEstimate,{price:money(p.materialPrice*row.quantity.amount)});
          }
        });
        if(main==='unsupported'){state.pending=[];state.last=[];state.rows=[];}
        return {html:advice, suggestions:[c.quote,c.phone]};
      }
      if (/^(?:tallinn|harjumaa|tartu|parnu|viimsi|saue|keila|rapla|maardu|таллинн?|тарту|пярну)(?:[,.\s].*)?$/.test(s) && !ids.length && !qs.length) return response('locationAck');
      if(!ids.length && /(?:jargmisel (?:nadal|kuu)|next (?:week|month)|следующе|oktoobr|novembr|detsembr)/.test(s)) return response('timingAck');
      if(ids.length && /(?:korter|tuba|room|apartment|квартир|комнат)/.test(s) && ids.some(function(id){return [0,1,2,3,4,23].indexOf(id)!==-1;}) && qs.length && !/(?:sein|lagi|lage|wall|ceiling|стен|потол)/.test(s)) return response('areaWarning',[c.quote,c.prepFollow]);
      if(ids.length) {
        if(ids.some(function(id){return prices[id].price===null;})) {state.pending=[];state.last=[];return response('unsupported');}
        if(/\d\s*(?:-|–|—|x|×)\s*\d/.test(s)) return response('multiple');
        // Split only at real clause boundaries, never at a decimal comma.
        var clauses=text.split(/[,;\n](?!\d)|\s+(?:ja|and|и)\s+/i), rows=[], seen=[];
        clauses.forEach(function(clause){
          var jobs=services(clause), quantitiesHere=quantities(clause,false);
          jobs.forEach(function(id){
            var quantity=jobs.length===1 && quantitiesHere.length===1 ? quantitiesHere[0] : null;
            if(seen.indexOf(id)===-1){seen.push(id);rows.push({id:id,quantity:quantity});}
            else if(quantity){var row=rows.find(function(r){return r.id===id;});if(!row.quantity) row.quantity=quantity;else row.quantity=null;}
          });
        });
        // Multiple jobs with a single quantity are ambiguous: ask rather than multiply all by it.
        if(rows.length===0) rows=ids.map(function(id){return {id:id,quantity:ids.length===1 && qs.length===1 ? qs[0] : null};});
        if(ids.length===1 && qs.length>1) rows=[{id:ids[0],quantity:null}];
        return renderRows(rows,state);
      }
      var pack=config.packages.find(function(p){return p.keywords.some(function(k){return s.indexOf(normalize(k))!==-1;});});
      if(pack) {
        state.pending=[];state.last=[];state.rows=[];
        var packageAdvice=pack===config.packages[1] ? c.topics.bathroom : c.estimateQuestion;
        return {html:'<strong>'+escape(pack.title)+'</strong><br>'+fill(messages.package,{price:money(pack.price),items:escape(pack.text)})+'<br><br>'+packageAdvice+'<br><small>'+c.disclaimer+'</small>', suggestions:[c.quote,c.materialsFollow]};
      }
      if(!qs.length) qs=quantities(text,state.pending.length===1 || state.last.length===1);
      if(qs.length) {
        var target=state.pending.length ? state.pending : state.last;
        if(target.length===1 && qs.length===1) return renderRows([{id:target[0],quantity:qs[0]}],state);
        return response(target.length>1 ? 'multiple' : 'clarify');
      }
      if(/(?:parket|lamina|porand|floor|parquet|паркет|ламинат)/.test(s)) return response('flooring');
      if(/(?:pakkumi|paring|quote|enquiry|предложен|запрос|смет)/.test(s)) return response('estimateQuestion',c.chips.slice(1,3));
      if(asksPrice && state.last.length===1) return renderRows(state.rows,state);
      if(asksPrice) return {html:messages.pricePrompt,suggestions:c.chips.slice(1,3)};
      return response('unknown',c.chips.slice(0,3));
    }
    return {answer:answer,createState:createState,copy:c,language:lang,catalog:prices};
  }

  function mount(config) {
    var engine=createEngine(config), c=engine.copy, state=createState();
    global.RenoveeriAssistant=engine;
    if(typeof document==='undefined') return engine;
    var currentScript=document.querySelector('script[src*="renoveeri-ai-chat.js"]');
    var assetRoot=currentScript ? new URL('../',currentScript.src) : new URL('/assets/',location.href);
    if(!document.getElementById('support-style')) {
      var link=document.createElement('link');link.id='support-style';link.rel='stylesheet';link.href=new URL('css/renoveeri-support.css?v=20261013',assetRoot).href;document.head.appendChild(link);
    }
    var bubble=document.getElementById('ai-bubble');
    if(!bubble){bubble=document.createElement('button');bubble.id='ai-bubble';document.body.appendChild(bubble);}
    bubble.type='button';bubble.className='rk-support-launcher';bubble.innerHTML='<span aria-hidden="true">💬</span> '+escape(c.open);
    bubble.setAttribute('aria-label',c.open);bubble.setAttribute('aria-controls','ai-chat');bubble.setAttribute('aria-expanded','false');
    var chat=document.getElementById('ai-chat');
    if(!chat){chat=document.createElement('section');chat.id='ai-chat';document.body.appendChild(chat);}
    if(chat.dataset.supportReady) return engine;
    chat.dataset.supportReady='1';chat.className='rk-support';chat.setAttribute('role','dialog');chat.setAttribute('aria-modal','false');chat.setAttribute('aria-labelledby','support-title');
    chat.hidden=true;chat.style.display='none';
    function route(key) {
      if(global.RenoveeriI18n) return global.RenoveeriI18n.route(key);
      var prefix=new URL('../',assetRoot).pathname;
      return prefix+({contact:{et:'kontakt',en:'en/contact',ru:'ru/kontakty'},prices:{et:'hinnakiri',en:'en/prices',ru:'ru/tseny'}}[key][config.language]);
    }
    var contact=route('contact')+'#vorm';
    chat.innerHTML='<header id="ai-header"><div><strong id="support-title">'+escape(c.title)+'</strong><small>'+escape(c.subtitle)+'</small></div><button type="button" id="ai-close" aria-label="'+escape(c.close)+'">×</button></header>'+
      '<div class="rk-support-disclosure">'+escape(c.disclosure)+'</div><div id="ai-messages" role="log" aria-live="polite" aria-relevant="additions" aria-label="'+escape(c.title)+'"></div><div class="rk-support-suggestions"></div>'+
      '<form class="rk-support-composer"><label class="rk-support-sr" for="ai-input">'+escape(c.placeholder)+'</label><input id="ai-input" maxlength="1500" autocomplete="off" placeholder="'+escape(c.placeholder)+'"><button type="submit" id="ai-send">'+escape(c.send)+'</button></form>'+
      '<div class="rk-support-links"><a data-support-transfer href="'+escape(contact)+'">'+escape(c.quote)+'</a><a href="tel:+37255515783">'+escape(c.phone)+'</a><a href="'+escape(route('prices'))+'">'+escape(c.pricing)+'</a><button type="button" data-support-reset>'+escape(c.reset)+'</button></div><small class="rk-support-privacy">'+escape(c.privacy)+'</small>';
    var input=chat.querySelector('#ai-input'),log=chat.querySelector('#ai-messages'),suggestions=chat.querySelector('.rk-support-suggestions');
    function add(sender,html,customer){var p=document.createElement('div');p.className='rk-support-message'+(customer?' rk-support-customer':'');p.innerHTML='<strong class="rk-support-sender">'+escape(sender)+'</strong><div>'+html+'</div>';log.appendChild(p);log.scrollTop=log.scrollHeight;}
    function buttons(items){suggestions.replaceChildren();items.forEach(function(label){var b=document.createElement('button');b.type='button';b.textContent=label;b.onclick=function(){if(label===c.quote){saveSummary();location.href=contact;}else if(label===c.phone){location.href='tel:+37255515783';}else send(label);};suggestions.appendChild(b);});}
    function send(text){text=String(text).trim().slice(0,1500);if(!text)return;add(config.messages.client,escape(text),true);input.value='';var result=engine.answer(text,state);add(c.agent,result.html);buttons(result.suggestions);input.focus();}
    function reset(){state=createState();log.replaceChildren();add(c.agent,escape(c.welcome));buttons(c.chips);}
    function saveSummary(){try{sessionStorage.setItem('rk-support-enquiry',JSON.stringify({created:Date.now(),text:c.summary+'\n'+state.transcript.join('\n')}));offerTransfer();}catch(e){/* The contact form remains usable when storage is disabled. */}}
    bubble.onclick=function(){chat.hidden=false;chat.style.display='flex';bubble.setAttribute('aria-expanded','true');input.focus();};
    function close(){chat.hidden=true;chat.style.display='none';bubble.setAttribute('aria-expanded','false');bubble.focus();}
    chat.querySelector('#ai-close').onclick=close;
    chat.addEventListener('keydown',function(e){if(e.key==='Escape'){e.preventDefault();close();}});
    chat.querySelector('form').onsubmit=function(e){e.preventDefault();send(input.value);};
    chat.querySelector('[data-support-reset]').onclick=function(){reset();input.focus();};
    chat.querySelector('[data-support-transfer]').onclick=saveSummary;
    reset();
    // Explicit hand-off only: never sends a request or overwrites the customer's form.
    function offerTransfer(){
      var form=document.getElementById('contactForm'),field=form && form.querySelector('textarea[name="message"]');
      if(!field) return;
      try{
        var stored=JSON.parse(sessionStorage.getItem('rk-support-enquiry') || 'null');
        if(stored && typeof stored.text==='string' && Date.now()-stored.created<30*60*1000){
          var previous=document.getElementById('support-transfer-enquiry');if(previous) previous.remove();
          var transfer=document.createElement('button');transfer.id='support-transfer-enquiry';transfer.type='button';transfer.className='rk-support-transfer';transfer.textContent=c.transfer;
          transfer.onclick=function(){field.value+=(field.value?'\n\n':'')+stored.text.slice(0,4000);field.dispatchEvent(new Event('input',{bubbles:true}));transfer.textContent=c.transferred;transfer.disabled=true;sessionStorage.removeItem('rk-support-enquiry');field.focus();};
          field.parentNode.insertBefore(transfer,field);
        }else sessionStorage.removeItem('rk-support-enquiry');
      }catch(e){/* Storage is optional. */}
    }
    offerTransfer();
    return engine;
  }
  global.RenoveeriSupport={mount:mount,createEngine:createEngine};
})(typeof window==='undefined' ? globalThis : window);
