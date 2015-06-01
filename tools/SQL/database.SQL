-- phpMyAdmin SQL Dump
-- version 3.4.5
-- http://www.phpmyadmin.net
--
-- Poèítaè: localhost
-- Vygenerováno: Sob 17. srp 2013, 17:06
-- Verze MySQL: 5.5.16
-- Verze PHP: 5.3.8

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET foreign_key_checks = 0;

--
-- Databáze: `udtranslator`
--

-- --------------------------------------------------------

--
-- Struktura tabulky `prekladkategorie`
--

CREATE TABLE IF NOT EXISTS `prekladkategorie` (
  `id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `nazev` varchar(45) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nazev_UNIQUE` (`nazev`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci AUTO_INCREMENT=3 ;

--
-- Vypisuji data pro tabulku `prekladkategorie`
--

INSERT INTO `prekladkategorie` (`id`, `nazev`) VALUES
(2, 'Intro'),
(1, 'Layout');

-- --------------------------------------------------------

--
-- Struktura tabulky `prekladlokalizace`
--

CREATE TABLE IF NOT EXISTS `prekladlokalizace` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uzivatel_id` mediumint(8) unsigned NOT NULL,
  `sysjazyk_id` tinyint(3) unsigned NOT NULL,
  `prekladzaklad_id` int(10) unsigned NOT NULL,
  `preklad` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `datumCas` datetime NOT NULL,
  `forma` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_prekladlokalizace_prekladzaklad` (`prekladzaklad_id`),
  KEY `FK_prekladlokalizace_sysjazyk` (`sysjazyk_id`),
  KEY `FK_prekladlokalizace_uzivatel` (`uzivatel_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci AUTO_INCREMENT=43 ;

--
-- Vypisuji data pro tabulku `prekladlokalizace`
--

INSERT INTO `prekladlokalizace` (`id`, `uzivatel_id`, `sysjazyk_id`, `prekladzaklad_id`, `preklad`, `datumCas`, `forma`) VALUES
(40, 15, 1, 18, 'Odhlásit %s se', '2013-08-16 20:47:34', 1),
(41, 15, 1, 18, 'Odhlásit %s se', '2013-08-16 20:47:35', 2),
(42, 15, 1, 18, 'Odhlásit %s se', '2013-08-16 20:47:35', 3);

-- --------------------------------------------------------

--
-- Struktura tabulky `prekladopravneni`
--

CREATE TABLE IF NOT EXISTS `prekladopravneni` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `sysjazyk_id` tinyint(3) unsigned NOT NULL,
  `uzivatel_id` mediumint(8) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_prekladopravneni_sysjazyk` (`sysjazyk_id`),
  KEY `FK_prekladopravneni_uzivatel` (`uzivatel_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci AUTO_INCREMENT=4 ;

--
-- Vypisuji data pro tabulku `prekladopravneni`
--

INSERT INTO `prekladopravneni` (`id`, `sysjazyk_id`, `uzivatel_id`) VALUES
(3, 1, 15);

-- --------------------------------------------------------

--
-- Struktura tabulky `prekladzaklad`
--

CREATE TABLE IF NOT EXISTS `prekladzaklad` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `prekladkategorie_id` tinyint(3) unsigned DEFAULT NULL,
  `retezec` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `retezec_md5` binary(16) NOT NULL,
  `neprelozitelne` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `retezec_md5_UNIQUE` (`retezec_md5`),
  KEY `FK_prekladzaklad_prekladkategorie` (`prekladkategorie_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci AUTO_INCREMENT=20 ;

--
-- Vypisuji data pro tabulku `prekladzaklad`
--

INSERT INTO `prekladzaklad` (`id`, `prekladkategorie_id`, `retezec`, `retezec_md5`) VALUES
(18, 1, 'Odhlásit %s se', '???4Vu??f7-??'),
(19, 1, 'Odhlášení se', 'v????2??@?m\0');

--
-- Spouštì `prekladzaklad`
--
DROP TRIGGER IF EXISTS `prekladzaklad_bi`;
DELIMITER //
CREATE TRIGGER `prekladzaklad_bi` BEFORE INSERT ON `prekladzaklad`
 FOR EACH ROW SET NEW.`retezec_md5` = UNHEX(MD5(NEW.`retezec`))
//
DELIMITER ;
DROP TRIGGER IF EXISTS `prekladzaklad_bu`;
DELIMITER //
CREATE TRIGGER `prekladzaklad_bu` BEFORE UPDATE ON `prekladzaklad`
 FOR EACH ROW SET NEW.`retezec_md5` = UNHEX(MD5(NEW.`retezec`))
//
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktura tabulky `sysjazyk`
--

CREATE TABLE IF NOT EXISTS `sysjazyk` (
  `id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `nazev` varchar(30) COLLATE utf8_czech_ci NOT NULL,
  `zkratka` varchar(3) COLLATE utf8_czech_ci NOT NULL,
  `forma` varchar(200) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UQ_SysJazyk_nazev` (`nazev`),
  UNIQUE KEY `zkratka_UNIQUE` (`zkratka`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci AUTO_INCREMENT=2 ;

--
-- Vypisuji data pro tabulku `sysjazyk`
--

INSERT INTO `sysjazyk` (`id`, `nazev`, `zkratka`, `forma`) VALUES
(1, 'Èeština', 'cs', 'nplurals=3; plural=((n==1) ? 0 : (n>=2 && n<=4 ? 1 : 2))');

-- --------------------------------------------------------

--
-- Struktura tabulky `uzivatel`
--

CREATE TABLE IF NOT EXISTS `uzivatel` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `login` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci AUTO_INCREMENT=3287 ;

--
-- Vypisuji data pro tabulku `uzivatel`
--

INSERT INTO `uzivatel` (`id`, `login`) VALUES
(15, 'stepan');

--
-- Omezení pro exportované tabulky
--

--
-- Omezení pro tabulku `prekladlokalizace`
--
ALTER TABLE `prekladlokalizace`
  ADD CONSTRAINT `FK_prekladlokalizace_uzivatel` FOREIGN KEY (`uzivatel_id`) REFERENCES `uzivatel` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `FK_prekladlokalizace_prekladzaklad` FOREIGN KEY (`prekladzaklad_id`) REFERENCES `prekladzaklad` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT `FK_prekladlokalizace_sysjazyk` FOREIGN KEY (`sysjazyk_id`) REFERENCES `sysjazyk` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Omezení pro tabulku `prekladopravneni`
--
ALTER TABLE `prekladopravneni`
  ADD CONSTRAINT `FK_prekladopravneni_uzivatel` FOREIGN KEY (`uzivatel_id`) REFERENCES `uzivatel` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT `FK_prekladopravneni_sysjazyk` FOREIGN KEY (`sysjazyk_id`) REFERENCES `sysjazyk` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Omezení pro tabulku `prekladzaklad`
--
ALTER TABLE `prekladzaklad`
  ADD CONSTRAINT `FK_prekladzaklad_prekladkategorie` FOREIGN KEY (`prekladkategorie_id`) REFERENCES `prekladkategorie` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;
