-- Vali esmalt õige andmebaas. Tee contacts tabelist varukoopia enne migratsiooni.
-- Uuendab ka olemasolevad tekstiveerud; ainult tabeli vaikimisi kodeeringust ei piisa.
-- Korduv käivitamine on lubatud. Vajab contacts tabeli ALTER õigust.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE contacts CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Juba küsimärkideks asendatud teksti see ei taasta. Kasuta algset e-kirja/varukoopiat.
