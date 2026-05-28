<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * German language strings for qtype_accounting.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Buchungssatz';
$string['pluginname_help'] = 'Ein Fragetyp, bei dem Studierende Buchungssätze erstellen, indem sie Soll- und Haben-Konten auswählen und Beträge eingeben.';
$string['pluginname_link'] = 'question/type/accounting';
$string['pluginnameadding'] = 'Buchungssatz-Frage hinzufügen';
$string['pluginnameediting'] = 'Buchungssatz-Frage bearbeiten';
$string['pluginnamesummary'] = 'Ein Fragetyp zum Üben von Buchungssätzen. Studierende wählen Konten aus einem Kontenplan und geben Soll-/Haben-Beträge ein.';

// Formularfelder.
$string['chartofaccounts'] = 'Kontenplan';
$string['chartofaccounts_help'] = 'Wählen Sie den Kontenplan aus, den Studierende für ihre Buchungen verwenden sollen.';
$string['chartofaccounts_section'] = 'Kontenplan';
$string['accountsindropdown'] = 'Anzahl zusätzlicher Konten in der Auswahlliste';
$string['accountsindropdown_help'] = 'Die Anzahl der zufälligen zusätzlichen Konten, die neben dem richtigen Konto in der Auswahlliste angezeigt werden sollen. Setzen Sie den Wert auf 0, um alle Konten des ausgewählten Kontenplans anzuzeigen. Beispiel: Bei Eingabe von 3 wird das richtige Konto plus 3 zufällige Konten angezeigt (4 insgesamt).';
$string['numberformat'] = 'Zahlenformat';
$string['extraentrydeduction'] = 'Abzug für überflüssige Konten und Werte';
$string['extraentrydeduction_help'] = 'Prozentualer Abzug pro zusätzlichem Konto, das der Studierende verwendet und nicht in der Musterlösung vorkommt. Jede Seite (Soll/Haben) wird unabhängig gezählt. Auf "Keine" setzen für keinen Abzug. Die Punktzahl kann nicht unter 0 fallen.';
$string['allornothinggrading'] = 'Keine Teilpunkte vergeben';
$string['allornothinggrading_help'] = 'Wenn aktiviert, erhält der Studierende nur dann Punkte, wenn alle Buchungseinträge vollständig korrekt sind. Bei einem Fehler (auch nur bei einem Betrag) wird die gesamte Frage mit 0 Punkten bewertet.';
$string['allornothinggrading_notice'] = 'Hinweis: Für diese Frage werden keine Teilpunkte vergeben. Alle Eingaben müssen vollständig korrekt sein, um Punkte zu erhalten.';
$string['numberformat_help'] = 'Wähle das Format für das Dezimal- und das Tausendertrennzeichen. Entweder #.###,00 oder #,###.00';
$string['numberformat_de'] = '#.###,00';
$string['numberformat_us'] = '#,###.00';
$string['nochartselected'] = '-- Kein Kontenplan ausgewählt --';
$string['allowmultipleentries'] = 'Mehrere Buchungszeilen erlauben';
$string['allowmultipleentries_help'] = 'Wenn aktiviert, können Studierende mehrere Buchungszeilen eingeben (z.B. für zusammengesetzte Buchungssätze).';
$string['maxentries'] = 'Maximale Anzahl Buchungszeilen';
$string['correctanswer'] = 'Musterlösung';
$string['entry'] = 'Buchungszeile {no}';
$string['addentry'] = 'Buchungszeile hinzufügen';
$string['adddebitentry'] = 'Sollbuchung hinzufügen';
$string['addcreditentry'] = 'Habenbuchung hinzufügen';
$string['deleteentry'] = 'Löschen';

// Kontenfelder.
$string['per'] = 'Per';
$string['an'] = 'an';
$string['debit'] = 'Soll';
$string['credit'] = 'Haben';
$string['account'] = 'Konto';
$string['debitaccount'] = 'Sollkonto';
$string['debitamount'] = 'Sollbetrag';
$string['creditaccount'] = 'Habenkonto';
$string['creditamount'] = 'Habenbetrag';
$string['weight'] = 'Gewicht';
$string['weight_help'] = 'Gewichtung für dieses Feld bei der Bewertung (1, 2 oder 3). Höhere Werte bedeuten mehr Punkte. Beispiel: Hat ein Konto das Gewicht 3 und der zugehörige Betrag das Gewicht 1, ist das Konto dreimal so wichtig wie der Betrag für die Bewertung.';
$string['weight_tooltip'] = 'Die Eingabefelder (Konten, Beträge) können mit einem Gewicht von 1–3 versehen werden. Das Gewicht ist für die Berechnung der Teilpunkte relevant. Teilpunkte werden wie folgt berechnet: (Σ Gewichte richtiger Eingabefelder) ÷ (Σ Gewichte aller Eingabefelder) × Erreichbare Punkte.

Wichtig: Konto und Betrag sind verknüpft – ein richtiger Betrag zählt nur, wenn auch das zugehörige Konto korrekt ausgewählt wurde. Ein richtig eingetragenes Konto ohne korrekten Betrag wird hingegen anteilig gewertet.

Die Gewichtung wird überschrieben bzw. hat keinen Effekt, sobald die Option „Keine Teilpunkte vergeben" aktiviert ist.';
$string['accountnumber'] = 'Kontobezeichnung';
$string['amount'] = 'Betrag';
$string['selectaccount'] = '-- Konto auswählen --';
$string['enteraccount'] = 'Kontonummer eingeben';
$string['noaccountselected'] = 'Kein Konto ausgewählt';
$string['grade'] = 'Bewertung (%)';
$string['grade_help'] = 'Der Prozentsatz der Gesamtpunktzahl, den dieser Eintrag wert ist. Alle Bewertungen müssen zusammen genau 100% ergeben.';
$string['explanation'] = 'Erklärung';
$string['explanation_help'] = 'Eine optionale Erklärung für diesen Buchungssatz, die den Studierenden bei der Überprüfung der richtigen Antwort angezeigt wird.';

// Feedback.
$string['correctansweris'] = 'Die richtige Antwort lautet:';
$string['pleaseenteranswer'] = 'Bitte geben Sie mindestens einen vollständigen Buchungssatz ein.';
$string['allcorrect'] = 'Alle Einträge sind korrekt!';
$string['debitincorrect'] = 'Die Soll-Seite ist nicht korrekt.';
$string['debitpartiallyincorrect'] = 'Die Soll-Seite ist teilweise nicht korrekt.';
$string['debithasextraaccounts'] = 'Die Soll-Seite enthält überflüssige Konten.';
$string['creditincorrect'] = 'Die Haben-Seite ist nicht korrekt.';
$string['creditpartiallyincorrect'] = 'Die Haben-Seite ist teilweise nicht korrekt.';
$string['credithasextraaccounts'] = 'Die Haben-Seite enthält überflüssige Konten.';

// Validierungsfehler.
$string['err_noentries'] = 'Bitte geben Sie mindestens eine Buchungszeile mit Konto und Betrag ein.';
$string['err_debitrequired'] = 'Ein Soll-Konto ist erforderlich, wenn ein Haben-Konto angegeben ist.';
$string['err_creditrequired'] = 'Ein Haben-Konto ist erforderlich, wenn ein Soll-Konto angegeben ist.';
$string['err_negativeamount'] = 'Beträge müssen positiv sein.';
$string['err_minentries'] = 'Die maximale Anzahl der Buchungszeilen muss mindestens 1 sein.';
$string['err_maxentries'] = 'Die maximale Anzahl der Buchungszeilen darf {$a} nicht überschreiten.';
$string['err_creditamountrequired'] = 'Der Haben-Betrag ist erforderlich.';
$string['err_debitamountrequired'] = 'Der Soll-Betrag ist erforderlich, wenn ein Soll-Konto ausgewählt ist.';
$string['err_graderequired'] = 'Die Bewertung ist erforderlich.';
$string['err_gradeinvalid'] = 'Die Bewertung muss zwischen 0 und 100 liegen.';
$string['err_gradesumnotcomplete'] = 'Die Summe aller Bewertungen muss genau 100% betragen. Aktuelle Summe: {$a}%';
$string['err_chartrequired'] = 'Bitte wählen Sie einen Kontenplan aus.';
$string['err_accountsindropdown_negative'] = 'Die Anzahl der Konten in der Auswahlliste darf nicht negativ sein.';
$string['err_extraentrydeduction_range'] = 'Der Abzug muss zwischen 0 und 100 liegen.';
$string['err_balancemismatch'] = 'Die Summe der Soll-Beträge muss der Summe der Haben-Beträge entsprechen.';
$string['err_debitaccountrequired'] = 'Bitte wählen Sie ein Konto aus oder entfernen Sie diesen Eintrag.';
$string['err_creditaccountrequired'] = 'Bitte wählen Sie ein Konto aus oder entfernen Sie diesen Eintrag.';

// Kontenplanverwaltung.
$string['managecharts'] = 'Kontenpläne verwalten';
$string['addchart'] = 'Neuen Kontenplan hinzufügen';
$string['editchart'] = 'Kontenplan bearbeiten';
$string['deletechart'] = 'Kontenplan löschen';
$string['chartname'] = 'Name des Kontenplans';
$string['chartdescription'] = 'Beschreibung';
$string['importaccounts'] = 'Konten aus CSV importieren';
$string['exportaccounts'] = 'Konten als CSV exportieren';

// Datenschutz.
$string['privacy:metadata:qtype_accounting_charts'] = 'Die Kontenpläne-Tabelle speichert, wer den jeweiligen Kontenplan zuletzt geändert hat.';
$string['privacy:metadata:qtype_accounting_charts:usermodified'] = 'Die ID des Benutzers, der den Kontenplan zuletzt geändert hat.';

// Berechtigungen.
$string['accounting:managecharts'] = 'Kontenpläne verwalten';

// Kontenplanverwaltung.
$string['nocharts'] = 'Es wurden noch keine Kontenpläne erstellt.';
$string['accounts'] = 'Konten';
$string['chartcreated'] = 'Kontenplan erfolgreich erstellt.';
$string['chartdeleted'] = 'Kontenplan erfolgreich gelöscht.';
$string['chartrenamed'] = 'Kontenplan erfolgreich umbenannt.';
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
$string['chartnotfound'] = 'Kontenplan nicht gefunden.';
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
$string['filereaderror'] = 'Fehler beim Lesen der Datei. Bitte versuchen Sie es erneut.';
$string['importsuccess'] = 'Import erfolgreich! ';
$string['entriesimported'] = 'Buchungen importiert.';
$string['importerror'] = 'Importfehler: ';
$string['csvempty'] = 'CSV-Daten sind leer.';
$string['csvnodata'] = 'Keine Kontobezeichnungen gefunden. Jede nicht-leere Zeile wird als ein Kontoname behandelt.';
$string['csvinvalidformat'] = 'Die Kontodaten konnten nicht verarbeitet werden. Jede Zeile sollte eine Kontobezeichnung sein.';
$string['csvnoentries'] = 'Keine gültigen Buchungen in den CSV-Daten gefunden.';
$string['importedchart'] = 'Importierter Kontenplan';
$string['importedchartdesc'] = 'Kontenplan aus CSV-Daten importiert.';
$string['autocalculatedfromfractions'] = 'Automatisch berechnet aus der Summe der Punktzahlen';
$string['selectdebitaccountfirst'] = 'Bitte zuerst ein Sollkonto auswählen';
$string['distributegradesequally'] = 'Gleichmäßig verteilen';

// Kontenplan-Import aus CSV.
$string['importchartfromcsv'] = 'Kontenplan aus CSV importieren';
$string['importchart'] = 'Kontenplan importieren';
$string['csvfilerequired'] = 'Eine CSV-Datei ist erforderlich, um einen Kontenplan zu erstellen.';
$string['csvfilehelp'] = 'Laden Sie eine Textdatei mit einer Kontobezeichnung pro Zeile hoch. Der Kontenplanname wird aus dem Dateinamen abgeleitet oder automatisch generiert.';
$string['csvfile_help'] = 'Laden Sie eine Textdatei mit Kontobezeichnungen hoch.<br><br>
<strong>Format:</strong> Eine Kontobezeichnung pro Zeile.<br>
Leere Zeilen werden ignoriert. Doppelte Namen werden übersprungen.<br><br>
Beispiel:<br>
<code>1200 Bank</code><br>
<code>8400 Erlöse 19% USt</code><br>
<code>1000 Kasse</code><br><br>
Maximale Dateigröße: 2 MB<br><br>
<strong>Unterstützte Zeichenkodierungen:</strong> UTF-8 und Windows-1252 (gängiger Excel-Export).';
$string['overrideexisting'] = 'Bestehenden Kontenplan überschreiben';
$string['overrideexistingdesc'] = 'Bestehenden Kontenplan mit gleichem Namen ersetzen';
$string['overrideexisting_help'] = 'Wenn aktiviert und ein Kontenplan mit demselben Namen bereits existiert, wird dieser gelöscht und durch den importierten Kontenplan ersetzt. Warnung: Dies löscht den bestehenden Kontenplan und alle seine Konten dauerhaft.';
$string['chartexists_enableoverride'] = 'Ein Kontenplan mit dem Namen "{$a}" existiert bereits. Aktivieren Sie "Bestehenden Kontenplan überschreiben", um ihn zu ersetzen.';
$string['chartimportsuccess'] = '{$a} Konten erfolgreich importiert';
$string['witherrors'] = 'mit Fehlern';
$string['chartimportfailed'] = 'Kontenplan-Import fehlgeschlagen';
$string['importlineerror'] = 'Fehler in Zeile {$a}';
$string['importdate'] = 'Importdatum';
$string['uploadchartcsv'] = 'Kontenplan hochladen (CSV)';
$string['uploadchartcsv_btn'] = 'Hochladen';
$string['uploadchartcsv_help'] = 'Laden Sie eine Textdatei hoch, um einen neuen Kontenplan für diesen Kurs zu erstellen. Der Kontenplan erscheint sofort nach dem Hochladen in der Auswahlliste. Format: eine Kontobezeichnung pro Zeile.';
$string['saveandcontinue'] = 'Speichern und weiter';

// Mobile-Ansicht.
$string['debitentries'] = 'Sollbuchungen';
$string['creditentries'] = 'Habenbuchungen';
