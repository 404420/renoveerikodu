<?php
declare(strict_types=1);
// Avalik sisenemisaadress. Autentimine toimub olemasolevas admin/login.php-s.
header('Cache-Control: no-store');
header('Location: /admin/login.php', true, 303);
exit;
