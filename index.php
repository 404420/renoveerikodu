<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require "config.php";

$page = $_GET["page"] ?? "home";
$scrollTo = "";

if (isset($_GET["scroll"]) && $_GET["scroll"] === "contact") {
    $scrollTo = "contact";
}

$successMessage = "";

// 1️⃣ Kui tuldi redirectiga tagasi
if (isset($_GET["success"])) {
    $successMessage = "Päring saadetud edukalt!";
}

// 2️⃣ Kui vorm saadeti
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $phone = trim($_POST["phone"] ?? '');
    $address = trim($_POST["address"] ?? '');
    $message = trim($_POST["message"] ?? '');

    $uploadedFiles = [];

    $allowedExtensions = [
        'pdf','doc','docx','xls','xlsx','csv',
        'jpg','jpeg','png',
        'dwg','dxf','zip'
    ];

    $allowedMimeTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv',
        'image/jpeg',
        'image/png',
        'application/zip',
        'application/x-zip-compressed'
    ];

    $maxFileSize = 15 * 1024 * 1024;
    $uploadDir = __DIR__ . "/uploads/";
    $publicPath = "uploads/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (!empty($_FILES['attachments']['name'][0])) {

        foreach ($_FILES['attachments']['tmp_name'] as $key => $tmpName) {

            if ($key >= 10) break;

            if ($_FILES['attachments']['error'][$key] === 0) {

                $originalName = $_FILES['attachments']['name'][$key];
                $fileSize = $_FILES['attachments']['size'][$key];
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $fileType = mime_content_type($tmpName);

                if (!in_array($extension, $allowedExtensions)) continue;
                if (!in_array($fileType, $allowedMimeTypes)) continue;
                if ($fileSize > $maxFileSize) continue;

                $newName = time() . "_" . uniqid() . "." . $extension;
                $targetFile = $uploadDir . $newName;

                if (move_uploaded_file($tmpName, $targetFile)) {
                    $uploadedFiles[] = $publicPath . $newName;
                }
            }
        }
    }

    $filePath = !empty($uploadedFiles) ? json_encode($uploadedFiles) : null;

    $stmt = $conn->prepare("INSERT INTO contacts (name, email, phone, address, message, file_path) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $name, $email, $phone, $address, $message, $filePath);
    $stmt->execute();

    // 🔥 Redirect pärast salvestust
    header("Location: index.php?success=1#contact");
    exit;
}
?>

<!DOCTYPE HTML>
<!--
	Spectral by HTML5 UP
	html5up.net | @ajlkn
	Free for personal and commercial use under the CCA 3.0 license (html5up.net/license)
-->
<html>
	<head>
		<title>Renoveeri Kodu</title>
		<link rel="icon" type="image/x-icon" href="/favicon.ico?v=1">
        <link rel="shortcut icon" href="/favicon.ico?v=1">
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no" />
		<link rel="stylesheet" href="assets/css/main.css" />
		<noscript><link rel="stylesheet" href="assets/css/noscript.css" /></noscript>
	</head>

	

	<body class="is-preload">

		<!-- Page Wrapper -->
			<div id="page-wrapper">

				<!-- Header -->
					<header id="header" class="alt">
						<h1><a href="index.php">Renoveeri Kodu</a></h1>
						
	<!-- LOGO vasakul -->
	<!--<div class="hero-logo">
		<img src="images/logo.png" alt="Renoveeri Kodu">
	</div> -->
						<nav id="nav">
							<ul>
								<li class="special">
									<a href="#menu" class="menuToggle"><span>Menüü</span></a>
									<div id="menu">
										<ul>
											<li><a href="index.html">Avaleht</a></li>
											<li class="submenu">
											<div class="submenu-header">
											<span>Teenused</span>
											<span class="submenu-arrow">▸</span>
											</div>
											<ul class="submenu-items">
											<li><a href="plaatimine.html">Plaatimine</a></li>
											<li><a href="maalritood.html">Maalritööd</a></li>
											<li><a href="ledlahendused.html">LED lahendused</a></li>
											<li><a href="seinapaneelidepaigaldus.html">Seinapaneelide paigaldus</a></li>
											<li><a href="parketipaigaldus.html">Parketi paigaldus</a></li>
											<li><a href="tapeetimine.html">Tapeetimine</a></li>
											<li><a href="kipsitood.html">Kipsitööd</a></li>	
											<li><a href="tellingutepaigaldus.html">Tellingute paigaldus</a></li>		
											<li><a href="betoonitood.html">Betooni tööd</a></li>
											<li><a href="vundamenditood.html">Vundamendi tööd </a></li>
											<li><a href="fassaaditood.html">Fassaadi tööd</a></li>	
											<li><a href="katusetood.html">Katuse tööd </a></li>	
											<li><a href="lumetood.html">Lume tööd </a></li>																							
										</ul>
											</li>
											<li><a href="tehtudtood.html">Tehtud tööd</a></li>
											<li><a href="kontakt.php">Kontakt</a></li>
										</ul>
									</div>
								</li>
							</ul>
						</nav>
					</header>

				<!-- Banner -->
<section id="banner">

	<div class="inner">
		<h2>Renoveeri Kodu</h2>
							<p>Vii oma ehitusprojektid uuele tasemele.<br />
								</p>
								<ul class="actions special">
									<li><a href="/renoveerikodu/kontakt" class="button primary">Küsi pakkumist!</a></li>
								</ul>
						
						</div>
						<a href="#one" class="more scrolly">Uuri lisa</a>
					</section>
<section id="three" class="wrapper business-section">
<div class="inner">

<header class="major align-center">
<h2>Usaldusväärne ehituspartner sinu projektile</h2>
<p class="business-lead">
Kvaliteetsed ehitus- ja renoveerimislahendused, mis kestavad ajas.
</p>
</header>

<p class="align-center">
Pakume ehitus- ja renoveerimistöid era- ja äriklientidele, keskendudes hoonete renoveerimisele,
rekonstrueerimisele ning erinevatele ehitustöödele olemasolevatel objektidel.
Teeme tööd läbimõeldult ja korrektselt, arvestades nii projekti eripära kui ka kliendi soove.
</p>

<hr>

<div class="row gtr-50 gtr-uniform">

<!-- Miks valida meid -->
<div class="col-6 col-12-medium">
<h3>Miks valida meid?</h3>

<ul class="check-list">
<li>Kogemustega ja usaldusväärne meeskond</li>
<li>Selge tööprotsess ja kokkulepetest kinnipidamine</li>
<li>Korralik planeerimine ja täpne teostus</li>
<li>Personaalne lähenemine igale objektile</li>
<li>Püsiv ja kvaliteetne tulemus</li>
</ul>

<p>
Läheneme igale tööle vastutustundlikult ning peame oluliseks,
et lõpptulemus oleks praktiline, korrektne ja kauakestev.
</p>
</div>

<!-- Teenused -->
<div class="col-6 col-12-medium">
<h3>Meie teenused</h3>

<p>
Teostame ehitus- ja renoveerimistöid, lähtudes objekti eripärast
ning kokkulepitud töömahust.
</p>

<ul class="service-list">
<li>Hoonete renoveerimine ja rekonstrueerimine</li>
<li>Sise- ja välisviimistlustööd</li>
<li>Katusetööd ja fassaaditööd</li>
<li>Ümberehitused ja parandustööd</li>
</ul>

<ul class="actions">
	<li>
		<a href="#menu" id="open-services" class="button primary">
			Vaata teenuseid
		</a>
	</li>
</ul>

</div>

</div>

<hr>

<!-- Väärtused -->
<div class="align-center">
<h3>Meie väärtused</h3>
<p>
Hindame kvaliteetset teostust, ausat suhtlust ja kokkulepetest kinnipidamist.
Meie eesmärk on teha oma tööd korralikult ja nii, et tulemus kestaks.
</p>
</div>

<hr>



    <?php if (!empty($successMessage)): ?>
        <div style="background:#1f7a1f;padding:15px;margin-bottom:20px;color:white;border-radius:5px;">
            <?= $successMessage ?>
        </div>
    <?php endif; ?>
<div class="glass-container">
    <form method="POST" action="" enctype="multipart/form-data">
        <div class="row gtr-uniform">

            <div class="col-6 col-12-xsmall">
                <input type="text" name="name" placeholder="Nimi" required />
            </div>

            <div class="col-6 col-12-xsmall">
                <input type="email" name="email" placeholder="Email" required />
            </div>

            <div class="col-6 col-12-xsmall">
                <input type="tel" name="phone" placeholder="Tel nr (valikuline)" />
            </div>

            <div class="col-6 col-12-xsmall">
                <input type="text" name="address" placeholder="Aadress (valikuline)" />
            </div>

            <div class="col-12">
                <textarea name="message" placeholder="Sisesta sõnum" rows="6" required></textarea>
            </div>

            <div class="col-12">
                <ul class="actions">
                    <li>
                        <input type="submit" value="Saada sõnum" class="primary" />
                    </li>
                    <li>
                        <input type="file" name="attachments[]" multiple
accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png,.dwg,.dxf,.zip">
                    </li>
                    <li>
                        <input type="reset" value="Reset" />
                    </li>
                </ul>
            </div>

        </div>
    </form>
</div>

    <?php if(isset($_GET["newsletter"]) && $_GET["newsletter"]=="joined"): ?>
<div class="newsletter-success">
	Edukalt saadetud ✓
</div>
<?php endif; ?>
    <div class="glass-newsletter">

<!-- SUBSCRIBE FORM -->
<form action="newsletter.php" method="POST" class="newsletter-modern" id="subscribeForm">

<h3>Liitumine uudiskirjadega</h3>

<input 
type="email" 
name="email" 
placeholder="Sisesta oma email" 
required
class="newsletter-input"
>

<button type="submit" class="button primary fit">
Liitu uudiskirjaga
</button>

<label class="newsletter-consent">
<input type="checkbox" name="consent" required>
Nõustun uudiskirjade saamisega
</label>

</form> <!-- ⭐ SULGE SUBSCRIBE FORM SIIN -->


<!-- ===========================
     LOOBU UUDISKIRJAST
=========================== -->

<form id="unsubscribeForm" class="unsubscribe-form-pro">

<input 
type="email"
name="unsubscribe_email"
id="unsubscribeEmail"
placeholder="Sisesta oma email"
required
>

<button type="submit" class="button small fit">
Kinnita loobumine
</button>

<div id="unsubscribeMessage"></div>

</form>

</div>

</div>
</div>
</div>

</section>

				<!-- Footer -->
					<footer id="footer">
						<ul class="copyright">
							 <li>&copy; Renoveeri Kodu</li>
							 <li><a href="privaatsuspoliitika.html">Privaatsuspoliitika</a></li>
						</ul>
					</footer>

			</div>

		<!-- Scripts -->
			<script src="assets/js/jquery.min.js"></script>
			<script src="assets/js/jquery.scrollex.min.js"></script>
			<script src="assets/js/jquery.scrolly.min.js"></script>
			<script src="assets/js/browser.min.js"></script>
			<script src="assets/js/breakpoints.min.js"></script>
			<script src="assets/js/util.js"></script>
			<script src="assets/js/main.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {

	// Teenused dropdown (kogu rida klikitav)
	document.querySelectorAll(".submenu-header").forEach(header => {
		header.addEventListener("click", function () {
			this.parentElement.classList.toggle("open");
		});
	});

});
</script>

<script>
document.addEventListener("DOMContentLoaded", function(){

	const btn = document.getElementById("open-services");

	if(btn){
		btn.addEventListener("click", function(){

			setTimeout(() => {
				document.querySelector(".submenu")?.classList.add("open");
			}, 200);

		});
	}

});
</script>

<!-- AI Chat Script -->
<script>
document.addEventListener("DOMContentLoaded", function () {

	const bubble = document.getElementById("ai-bubble");
	const chat = document.getElementById("ai-chat");
	const close = document.getElementById("ai-close");
	const input = document.getElementById("ai-input");
	const sendBtn = document.getElementById("ai-send");
	const messages = document.getElementById("ai-messages");

	/* Ava / sulge */
	bubble.onclick = () => {
		chat.style.display = "flex";
		bubble.style.display = "none";
	};

	close.onclick = () => {
		chat.style.display = "none";
		bubble.style.display = "flex";
	};

	/* Enter + Saada */
	sendBtn.onclick = sendMessage;
	input.addEventListener("keypress", e => {
		if (e.key === "Enter") sendMessage();
	});

	function sendMessage() {
		const text = input.value.trim();
		if (!text) return;

		addMessage("Klient", text);
		input.value = "";

		setTimeout(() => {
			addMessage("AI", calculatePrice(text));
		}, 400);
	}

	function addMessage(sender, text) {
		messages.innerHTML += `<p><b>${sender}:</b> ${text}</p>`;
		messages.scrollTop = messages.scrollHeight;
	}

	// --- mälu kasutaja sisestuste jaoks ---
let pendingService = null;
let pendingAmount = null;

// --- hinnakiri ---
const services = [
	{ keywords: ["kips", "karkass"], price: 50, unit: "m2", name: "kipskarkass / vahesein" },
	{ keywords: ["plaat"], price: 75, unit: "m2", name: "plaatimine" },
	{ keywords: ["hüdro"], price: 22, unit: "m2", name: "hüdroisolatsioon" },
	{ keywords: ["värv", "värvimine"], price: 4.5, unit: "m2", name: "värvimine" },
	{ keywords: ["krunt"], price: 4.5, unit: "m2", name: "krunt värvimine" },
	{ keywords: ["pahtel"], price: 14, unit: "m2", name: "pahteldamine" },
	{ keywords: ["parkett"], price: 14, unit: "m2", name: "parketi paigaldus" },
	{ keywords: ["liist"], price: 9, unit: "jm", name: "liistude paigaldus" },
	{ keywords: ["kroh"], price: 9, unit: "m2", name: "krohvimine" },
	{ keywords: ["nakke"], price: 3, unit: "m2", name: "nakke paigaldus" },
	{ keywords: ["uks"], price: 80, unit: "tk", name: "korteri siseukse vahetus" },
	{ keywords: ["tapeedi eemaldus"], price: 3.5, unit: "m2", name: "tapeedi eemaldus" },
	{ keywords: ["tapeet"], price: 17, unit: "m2", name: "tapeetimine" },
	{ keywords: ["vuuk", "siliko"], price: 8, unit: "m2", name: "vuukimine / silikoonimine" },
	{ keywords: ["aken"], price: 20, unit: "jm", name: "akna põskede viimistlus" }
];

function findService(text) {
	return services.find(s => s.keywords.some(k => text.includes(k)));
}

function findAmount(text) {
	const match = text.match(/(\d+(?:[.,]\d+)?)/);
	return match ? parseFloat(match[1].replace(",", ".")) : null;
}

function calculatePrice(text) {
	const t = text.toLowerCase().trim();

	const foundService = findService(t);
	const foundAmount = findAmount(t);

	if (foundService) pendingService = foundService;
	if (foundAmount) pendingAmount = foundAmount;

	// --- kui pole teenust ---
	if (!pendingService) {
		return "Mis tööd soovid? (nt: värvimine, tapeetimine, kipsitööd, liistud)";
	}

	// --- kui pole kogust ---
	if (!pendingAmount) {
		if (pendingService.unit === "m2") return `Mitu m² ${pendingService.name}?`;
		if (pendingService.unit === "jm") return `Mitu jm ${pendingService.name}?`;
		if (pendingService.unit === "tk") return `Mitu tk ${pendingService.name}?`;
	}

	// --- arvuta ---
	const total = pendingAmount * pendingService.price;

	const result = `
<b>Teenuse hinnang</b><br>
Töö: ${pendingService.name}<br>
Kogus: ${pendingAmount} ${pendingService.unit}<br>
Hind: ${pendingService.price}€/ ${pendingService.unit}<br>
<b>Kokku: ${total.toFixed(2)}€</b>
`;

	// reset peale arvutust
	pendingService = null;
	pendingAmount = null;

	return result;
}
});
</script>

		<!-- AI Chat Bubble -->
<div id="ai-bubble">💬</div>

<div id="ai-chat">
  <div id="ai-header">
    Küsi hinnangut
    <span id="ai-close">✕</span>
  </div>
  <div id="ai-messages">
    <div><b>AI:</b> Tere! Küsi julgelt hinnangut töödele 😊</div>
  </div>
  <input id="ai-input" placeholder="Nt: 10m² maalritööd" />
  <button id="ai-send">Saada</button>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
	const currentPath = window.location.pathname.split("/").pop();

	// kõik menüü lingid
	document.querySelectorAll("#menu a").forEach(link => {
		const linkPath = link.getAttribute("href");

		if (linkPath === currentPath) {
			link.classList.add("active");

			// Kui on submenu link → ava submenu
			const submenu = link.closest(".submenu");
			if (submenu) {
				submenu.classList.add("open");
			}
		}
	});
});
</script>

<script>
window.addEventListener("load", function () {

    if (window.location.href.indexOf("success=1") > -1) {

        setTimeout(function () {

            var contact = document.getElementById("contact");

            if (contact) {
                contact.scrollIntoView({
                    behavior: "smooth",
                    block: "start"
                });
            }

        }, 800); // ootame kuni Spectral lõpetab oma scrolli
    }

});
document.addEventListener("DOMContentLoaded", function(){

	const menuToggle = document.querySelector(".menuToggle");
	const servicesSubmenu = document.querySelector(".submenu");

	if(menuToggle && servicesSubmenu){
		menuToggle.addEventListener("click", function(){
			setTimeout(() => {
				servicesSubmenu.classList.add("open");
			}, 200); // ootab kuni menüü avaneb
		});
	}

});
</script>
<script>
document.addEventListener("DOMContentLoaded", function(){

const btn = document.getElementById("toggleUnsub");
const form = document.getElementById("unsubscribeForm");

if(btn){
btn.addEventListener("click", function(){
form.style.display = form.style.display === "block" ? "none" : "block";
});
}

});
</script>
<?php if ($scrollTo === "contact"): ?>
<script>
window.addEventListener("load", function() {
    var el = document.getElementById("contact");
    if (el) {
        window.scrollTo({
            top: el.offsetTop - 50,
            behavior: "auto"
        });
    }
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function(){

const form = document.getElementById("unsubscribeForm");
const emailInput = document.getElementById("unsubscribeEmail");
const msg = document.getElementById("unsubscribeMessage");

form.addEventListener("submit", async function(e){
e.preventDefault(); // EI reloadi lehte

msg.innerHTML = "";

// email kontroll
const email = emailInput.value.trim();
const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

if(!emailRegex.test(email)){
msg.innerHTML = `
<div style="background:#d9534f;color:white;padding:12px;border-radius:6px;margin-top:15px;">
Palun sisesta korrektne email.
</div>`;
return;
}

// saadame backendile
const formData = new FormData();
formData.append("unsubscribe_email", email);

try{
const res = await fetch("unsubscribe-handler.php", {
method: "POST",
body: formData
});

const data = await res.json();

if(data.success){
msg.innerHTML = `
<div style="background:#1f7a1f;color:white;padding:12px;border-radius:6px;margin-top:15px;">
Edukalt loobusite uudiskirjadest ✓
</div>`;
form.reset();
}else{
msg.innerHTML = `
<div style="background:#d9534f;color:white;padding:12px;border-radius:6px;margin-top:15px;">
${data.message}
</div>`;
}

}catch(err){
msg.innerHTML = `
<div style="background:#d9534f;color:white;padding:12px;border-radius:6px;margin-top:15px;">
Serveri viga.
</div>`;
}

});

});
</script>

<?php endif; ?>
<a href="kontakt.php" class="floating-contact">
	Võta ühendust
</a>

<style>
.floating-contact{
	position:fixed;
	right:20px;
	bottom:80px;
	background:#ff6a00;
	color:white;
	padding:14px 15px;
	border-radius:50px;
	text-decoration:none;
	z-index:999;
	box-shadow:0 5px 20px rgba(0,0,0,0.2);
	font-weight:600;
}


</style>
<script>
document.addEventListener("DOMContentLoaded", function(){

const form = document.getElementById("unsubscribeForm");
if(!form) return;

form.addEventListener("submit", async function(e){
e.preventDefault();

const email = document.getElementById("unsubscribeEmail").value.trim();
const msg = document.getElementById("unsubscribeMessage");

msg.innerHTML = "";

const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

if(!emailRegex.test(email)){
msg.innerHTML = `<div class="unsub-error">Palun sisesta korrektne email</div>`;
return;
}

try{
const formData = new FormData();
formData.append("unsubscribe_email", email);

const res = await fetch("unsubscribe-handler.php", {
method: "POST",
body: formData
});

const data = await res.json();

if(data.success){
msg.innerHTML = `<div class="unsub-success">Edukalt loobusite uudiskirjadest ✓</div>`;
form.reset();
}else{
msg.innerHTML = `<div class="unsub-error">${data.message}</div>`;
}

}catch{
msg.innerHTML = `<div class="unsub-error">Serveri viga</div>`;
}

});

});
</script>
	</body>
</html>