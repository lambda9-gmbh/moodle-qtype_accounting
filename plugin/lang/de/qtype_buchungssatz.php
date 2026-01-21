<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * German language strings for qtype_buchungssatz.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Buchungssatz';
$string['pluginname_help'] = 'Ein Fragetyp, bei dem Studierende Buchungssätze erstellen, indem sie Soll- und Haben-Konten auswählen und Beträge eingeben.';
$string['pluginname_link'] = 'question/type/buchungssatz';
$string['pluginnameadding'] = 'Buchungssatz-Frage hinzufügen';
$string['pluginnameediting'] = 'Buchungssatz-Frage bearbeiten';
$string['pluginnamesummary'] = 'Ein Fragetyp zum Üben von Buchungssätzen. Studierende wählen Konten aus einem Kontenplan und geben Soll-/Haben-Beträge ein.';

// Formularfelder.
$string['chartofaccounts'] = 'Kontenplan';
$string['chartofaccounts_help'] = 'Wählen Sie den Kontenplan aus, den Studierende für ihre Buchungen verwenden sollen.';
$string['nochartselected'] = '-- Kein Kontenplan ausgewählt --';
$string['allowmultipleentries'] = 'Mehrere Buchungszeilen erlauben';
$string['allowmultipleentries_help'] = 'Wenn aktiviert, können Studierende mehrere Buchungszeilen eingeben (z.B. für zusammengesetzte Buchungssätze).';
$string['maxentries'] = 'Maximale Anzahl Buchungszeilen';
$string['correctanswer'] = 'Musterlösung';
$string['entry'] = 'Buchungszeile {no}';
$string['addentry'] = 'Buchungszeile hinzufügen';
$string['deleteentry'] = 'Löschen';

// Kontenfelder.
$string['soll'] = 'Soll';
$string['haben'] = 'Haben';
$string['account'] = 'Konto';
$string['accountnumber'] = 'Kontonummer';
$string['amount'] = 'Betrag';
$string['selectaccount'] = '-- Konto auswählen --';
$string['noaccountselected'] = 'Kein Konto ausgewählt';
$string['sollamount'] = 'Soll-Betrag';
$string['habenamount'] = 'Haben-Betrag';
$string['grade'] = 'Bewertung (%)';
$string['grade_help'] = 'Der Prozentsatz der Gesamtpunktzahl, den dieser Eintrag wert ist. Alle Bewertungen müssen zusammen genau 100% ergeben.';
$string['explanation'] = 'Erklärung';
$string['explanation_help'] = 'Eine optionale Erklärung für diesen Buchungssatz, die den Studierenden bei der Überprüfung der richtigen Antwort angezeigt wird.';

// Feedback.
$string['correctansweris'] = 'Die richtige Antwort lautet:';
$string['pleaseenteranswer'] = 'Bitte geben Sie mindestens einen vollständigen Buchungssatz ein.';

// Validierungsfehler.
$string['err_noentries'] = 'Bitte geben Sie mindestens eine Buchungszeile mit Konto und Betrag ein.';
$string['err_sollrequired'] = 'Ein Soll-Konto ist erforderlich, wenn ein Haben-Konto angegeben ist.';
$string['err_habenrequired'] = 'Ein Haben-Konto ist erforderlich, wenn ein Soll-Konto angegeben ist.';
$string['err_negativeamount'] = 'Beträge müssen positiv sein.';
$string['err_minentries'] = 'Die maximale Anzahl der Buchungszeilen muss mindestens 1 sein.';
$string['err_maxentries'] = 'Die maximale Anzahl der Buchungszeilen darf {$a} nicht überschreiten.';
$string['err_habenamountrequired'] = 'Der Haben-Betrag ist erforderlich.';
$string['err_sollbetragrequired'] = 'Der Soll-Betrag ist erforderlich, wenn ein Soll-Konto ausgewählt ist.';
$string['err_graderequired'] = 'Die Bewertung ist erforderlich.';
$string['err_gradeinvalid'] = 'Die Bewertung muss zwischen 0 und 100 liegen.';
$string['err_gradesumnotcomplete'] = 'Die Summe aller Bewertungen muss genau 100% betragen. Aktuelle Summe: {$a}%';

// Kontenplanverwaltung.
$string['managecharts'] = 'Kontenpläne verwalten';
$string['addchart'] = 'Neuen Kontenplan hinzufügen';
$string['editchart'] = 'Kontenplan bearbeiten';
$string['deletechart'] = 'Kontenplan löschen';
$string['chartname'] = 'Name des Kontenplans';
$string['chartdescription'] = 'Beschreibung';
$string['importaccounts'] = 'Konten aus CSV importieren';
$string['exportaccounts'] = 'Konten als CSV exportieren';
$string['accounttype'] = 'Kontenart';
$string['accounttype_asset'] = 'Aktivkonto';
$string['accounttype_liability'] = 'Passivkonto (Verbindlichkeiten)';
$string['accounttype_equity'] = 'Eigenkapitalkonto';
$string['accounttype_revenue'] = 'Ertragskonto';
$string['accounttype_expense'] = 'Aufwandskonto';

// Datenschutz.
$string['privacy:metadata'] = 'Das Buchungssatz-Fragetyp-Plugin speichert keine personenbezogenen Daten.';

// Berechtigungen.
$string['buchungssatz:managecharts'] = 'Kontenpläne verwalten';

// Einstellungen.
$string['settings'] = 'Buchungssatz-Einstellungen';
$string['defaultchart'] = 'Standard-Kontenplan';
$string['defaultchart_desc'] = 'Der Standard-Kontenplan für neue Fragen.';

// Kontenplanverwaltung.
$string['nocharts'] = 'Es wurden noch keine Kontenpläne erstellt.';
$string['accounts'] = 'Konten';
$string['chartcreated'] = 'Kontenplan erfolgreich erstellt.';
$string['chartdeleted'] = 'Kontenplan erfolgreich gelöscht.';
$string['defaultchartcreated'] = 'Standard-SKR03-Kontenplan erfolgreich erstellt.';
$string['imported'] = '{$a} Konten erfolgreich importiert.';
$string['confirmdelete'] = 'Sind Sie sicher, dass Sie diesen Kontenplan löschen möchten?';
$string['balanced'] = 'Ausgeglichen';

// Kontenverwaltung.
$string['editaccounts'] = 'Konten bearbeiten';
$string['addaccount'] = 'Konto hinzufügen';
$string['noaccounts'] = 'Noch keine Konten in diesem Kontenplan.';
$string['accountadded'] = 'Konto erfolgreich hinzugefügt.';
$string['accountupdated'] = 'Konto erfolgreich aktualisiert.';
$string['accountdeleted'] = 'Konto erfolgreich gelöscht.';
$string['confirmdeleteaccount'] = 'Sind Sie sicher, dass Sie dieses Konto löschen möchten?';
$string['accountname'] = 'Kontobezeichnung';

// CSV-Import.
$string['importfromcsv'] = 'Aus Excel/CSV importieren';
$string['importhelp'] = 'Laden Sie eine CSV-Datei mit Buchhaltungsdaten hoch. Unterstützte Formate:<br>
<strong>Vollständiges Format:</strong> Sollkonto, Sollkontoname, Sollbetrag, Habenkonto, Habenkontoname, Habenbetrag<br>
<strong>Kompaktes Format:</strong> Sollkonto, Sollbetrag, Habenkonto, Habenbetrag<br>
Verwenden Sie Tab, Semikolon oder Komma als Trennzeichen. Deutsches Zahlenformat (1.234,56) wird unterstützt.';
$string['csvdata'] = 'CSV/Excel-Daten';
$string['csvfile'] = 'CSV-Datei';
$string['importentries'] = 'Buchungen importieren';
$string['nocsverror'] = 'Die CSV-Datei ist leer.';
$string['nofileselected'] = 'Keine Datei ausgewählt';
$string['filereaderror'] = 'Fehler beim Lesen der Datei. Bitte versuchen Sie es erneut.';
$string['importsuccess'] = 'Import erfolgreich! ';
$string['entriesimported'] = 'Buchungen importiert.';
$string['importerror'] = 'Importfehler: ';
$string['csvempty'] = 'CSV-Daten sind leer.';
$string['csvnodata'] = 'CSV muss mindestens eine Kopfzeile und eine Datenzeile enthalten.';
$string['csvinvalidformat'] = 'Soll- und Habenkonto-Spalten konnten nicht erkannt werden. Bitte überprüfen Sie das CSV-Format.';
$string['csvnoentries'] = 'Keine gültigen Buchungen in den CSV-Daten gefunden.';
$string['importedchart'] = 'Importierter Kontenplan';
$string['importedchartdesc'] = 'Kontenplan aus CSV-Daten importiert.';
$string['autoCalculatedFromFractions'] = 'Automatisch berechnet aus der Summe der Punktzahlen';
$string['selectDebitAccountFirst'] = 'Bitte zuerst ein Sollkonto auswählen';
$string['choosefile'] = 'Datei auswählen';
$string['distributegradesequally'] = 'Gleichmäßig verteilen';
