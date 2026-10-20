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

$string['account'] = 'Konto';
$string['accountadded'] = 'Konto erfolgreich hinzugefügt.';
$string['accountdeleted'] = 'Konto erfolgreich gelöscht.';
$string['accountdeletewarning'] = 'Durch das Löschen dieses Kontos können in bestehenden Fragen Konten fehlen. Die Bewertung und die Anzeige gespeicherter Versuche können beeinträchtigt werden. Diese Aktion kann nicht rückgängig gemacht werden. Prüfen Sie die betroffenen Fragen, bevor Sie fortfahren.';
$string['accounting:managecharts'] = 'Kontenpläne verwalten';
$string['accountname'] = 'Kontobezeichnung';
$string['accountnumber'] = 'Kontobezeichnung';
$string['accounts'] = 'Konten';
$string['accountsindropdown'] = 'Anzahl zusätzlicher Konten in der Auswahlliste';
$string['accountsindropdown_help'] = 'Die Anzahl der zufälligen zusätzlichen Konten, die neben dem richtigen Konto in der Auswahlliste angezeigt werden sollen. Alle umfasst sämtliche Konten des ausgewählten Kontenplans. Ein leeres Feld oder die Eingabe von 0 wählt ebenfalls Alle. Beispiel: Bei Eingabe von 3 wird das richtige Konto plus 3 zufällige Konten angezeigt (4 insgesamt).';
$string['accountsonlyexception'] = 'Punkte-Modus: ein Punkt für richtige Konten ohne Beträge';
$string['accountsonlyexception_desc'] = 'Wenn aktiviert, erhalten Schülerinnen und Schüler im Punkte-Modus einen Punkt, wenn sie auf beiden Seiten genau die richtigen Konten nennen (keins fehlt, keins zu viel), aber keine Beträge eintragen – statt keinem Punkt, wenn nur vollständig richtige Paare zählen. Die Musterlösung der Lehrkraft enthält immer Konten und Beträge.';
$string['accountupdated'] = 'Konto erfolgreich aktualisiert.';
$string['accountusagewarning'] = 'Dieses Konto wird in folgenden Fragen oder deren gespeicherten Versuchen verwendet:';
$string['addaccount'] = 'Konto hinzufügen';
$string['addchart'] = 'Neuen Kontenplan hinzufügen';
$string['addcreditentry'] = 'Habenbuchung hinzufügen';
$string['adddebitentry'] = 'Sollbuchung hinzufügen';
$string['addentry'] = 'Buchungszeile hinzufügen';
$string['allcorrect'] = 'Alle Einträge sind korrekt!';
$string['allornothinggrading'] = 'Keine Teilpunkte vergeben';
$string['allornothinggrading_help'] = 'Wenn aktiviert, erhält der Studierende nur dann Punkte, wenn alle Buchungseinträge vollständig korrekt sind. Bei einem Fehler (auch nur bei einem Betrag) wird die gesamte Frage mit 0 Punkten bewertet.';
$string['allornothinggrading_notice'] = 'Hinweis: Für diese Frage werden keine Teilpunkte vergeben. Alle Eingaben müssen vollständig korrekt sein, um Punkte zu erhalten.';
$string['allowmultipleentries'] = 'Mehrere Buchungszeilen erlauben';
$string['allowmultipleentries_help'] = 'Wenn aktiviert, können Studierende mehrere Buchungszeilen eingeben (z.B. für zusammengesetzte Buchungssätze).';
$string['amount'] = 'Betrag';
$string['an'] = 'an';
$string['autocalculatedfromfractions'] = 'Automatisch berechnet aus der Summe der Punktzahlen';
$string['balanced'] = 'Ausgeglichen';
$string['chartcopied'] = 'Der Kontenplan wurde in diesen Kurs kopiert und kann jetzt angepasst werden.';
$string['chartcreated'] = 'Kontenplan erfolgreich erstellt.';
$string['chartdeleted'] = 'Kontenplan erfolgreich gelöscht.';
$string['chartdescription'] = 'Beschreibung';
$string['chartexists_enableoverride'] = 'Ein Kontenplan mit dem Namen "{$a}" existiert bereits. Aktivieren Sie "Bestehenden Kontenplan überschreiben", um ihn zu ersetzen.';
$string['chartimportfailed'] = 'Kontenplan-Import fehlgeschlagen';
$string['chartimportsuccess'] = '{$a} Konten erfolgreich importiert';
$string['chartname'] = 'Name des Kontenplans';
$string['chartnotfound'] = 'Kontenplan nicht gefunden.';
$string['chartofaccounts'] = 'Kontenplan';
$string['chartofaccounts_help'] = 'Wählen Sie den Kontenplan aus, den Studierende für ihre Buchungen verwenden sollen.';
$string['chartofaccounts_section'] = 'Kontenplan';
$string['chartoverlaydiscard'] = 'Ungespeicherte Eingaben verwerfen';
$string['chartoverlaydone'] = 'Fertig';
$string['chartoverlayhint'] = 'Änderungen gelten auch für andere Fragen mit demselben Kontenplan. Wählen Sie „Fertig“, um zur Frage zurückzukehren.';
$string['chartoverlaysaving'] = 'Bitte warten Sie, bis Ihre Änderungen gespeichert wurden.';
$string['chartoverlayunsaved'] = 'Möchten Sie Ihre ungespeicherten Eingaben verwerfen und zur Frage zurückkehren? Bereits gespeicherte Änderungen bleiben erhalten.';
$string['chartoverlayunsavedtitle'] = 'Ungespeicherte Änderungen';
$string['chartrenamed'] = 'Kontenplan erfolgreich umbenannt.';
$string['confirmdelete'] = 'Sind Sie sicher, dass Sie den Kontenplan "{$a}" endgültig löschen möchten? Alle Konten dieses Kontenplans werden ebenfalls gelöscht. Bestehende Fragen funktionieren danach möglicherweise nicht mehr korrekt. Die Bewertung und die Anzeige gespeicherter Versuche können beeinträchtigt werden. Diese Aktion kann nicht rückgängig gemacht werden. Prüfen Sie die betroffenen Fragen, bevor Sie fortfahren.';
$string['confirmdeleteaccount'] = 'Sind Sie sicher, dass Sie dieses Konto löschen möchten?';
$string['copytocourse'] = 'In diesen Kurs kopieren';
$string['correctanswer'] = 'Musterlösung';
$string['correctansweris'] = 'Die richtige Antwort lautet:';
$string['createchart'] = 'Kontenplan erstellen';
$string['createchart_help'] = 'Geben Sie einen Namen ein, um einen leeren Kontenplan zu erstellen. Anschließend können Sie Konten direkt in Moodle hinzufügen oder aus einer CSV- oder ODS-Datei importieren.';
$string['credit'] = 'Haben';
$string['creditaccount'] = 'Habenkonto';
$string['creditamount'] = 'Habenbetrag';
$string['creditentries'] = 'Habenbuchungen';
$string['credithasextraaccounts'] = 'Die Haben-Seite enthält überflüssige Konten.';
$string['creditincorrect'] = 'Die Haben-Seite ist nicht korrekt.';
$string['creditpartiallyincorrect'] = 'Die Haben-Seite ist teilweise nicht korrekt.';
$string['csvdata'] = 'CSV/Excel-Daten';
$string['csvempty'] = 'CSV-Daten sind leer.';
$string['csvfile'] = 'CSV-, Text- oder ODS-Datei';
$string['csvfile_help'] = 'Laden Sie eine CSV-, Text- oder ODS-Datei mit Kontobezeichnungen hoch.<br><br>
<strong>CSV/Text:</strong> Eine Kontobezeichnung pro Zeile.<br>
<strong>ODS:</strong> Eine Kontobezeichnung pro Zeile in Spalte A des ersten Tabellenblatts. Weitere Spalten und Tabellenblätter werden ignoriert.<br>
Leere Zeilen werden ignoriert. Doppelte Namen werden übersprungen.<br><br>
Beispiel:<br>
<code>1200 Bank</code><br>
<code>8400 Erlöse 19% USt</code><br>
<code>1000 Kasse</code><br><br>
Maximale Dateigröße: 2 MB<br><br>
<strong>Unterstützte Zeichenkodierungen:</strong> UTF-8 und Windows-1252 (gängiger Excel-Export).';
$string['csvfilehelp'] = 'Laden Sie eine Textdatei mit einer Kontobezeichnung pro Zeile hoch. Der Kontenplanname wird aus dem Dateinamen abgeleitet oder automatisch generiert.';
$string['csvfilerequired'] = 'Für den Import ist eine CSV-, Text- oder ODS-Datei erforderlich.';
$string['csvinvalidformat'] = 'Die Kontodaten konnten nicht verarbeitet werden. Jede Zeile sollte eine Kontobezeichnung sein.';
$string['csvnodata'] = 'Keine Kontobezeichnungen gefunden. Jede nicht-leere Zeile wird als ein Kontoname behandelt.';
$string['csvnoentries'] = 'Keine gültigen Kontobezeichnungen in der Datei gefunden.';
$string['debit'] = 'Soll';
$string['debitaccount'] = 'Sollkonto';
$string['debitamount'] = 'Sollbetrag';
$string['debitentries'] = 'Sollbuchungen';
$string['debithasextraaccounts'] = 'Die Soll-Seite enthält überflüssige Konten.';
$string['debitincorrect'] = 'Die Soll-Seite ist nicht korrekt.';
$string['debitpartiallyincorrect'] = 'Die Soll-Seite ist teilweise nicht korrekt.';
$string['defaultgradingmode'] = 'Standard-Bewertungsart';
$string['defaultgradingmode_desc'] = 'Bewertungsart, die bei neuen Buchungssatz-Fragen vorausgewählt ist. Lehrkräfte können sie je Frage ändern.';
$string['deleteaccount'] = 'Konto löschen';
$string['deletechart'] = 'Kontenplan löschen';
$string['deleteentry'] = 'Löschen';
$string['distributegradesequally'] = 'Gleichmäßig verteilen';
$string['editaccounts'] = 'Konten bearbeiten';
$string['editchart'] = 'Kontenplan bearbeiten';
$string['enteraccount'] = 'Kontonummer eingeben';
$string['entriesimported'] = 'Buchungen importiert.';
$string['entry'] = 'Buchungszeile {no}';
$string['entryrows'] = 'Anzahl der Buchungszeilen';
$string['err_accountsindropdown'] = 'Geben Sie Alle oder eine nicht negative ganze Zahl ein.';
$string['err_accountsindropdown_negative'] = 'Die Anzahl der Konten in der Auswahlliste darf nicht negativ sein.';
$string['err_accountunavailable'] = 'Dieser Kontenplan oder dieses Konto ist nicht mehr verfügbar. Bitte wählen Sie einen Ersatz.';
$string['err_balancemismatch'] = 'Die Summe der Soll-Beträge muss der Summe der Haben-Beträge entsprechen.';
$string['err_chartdeleted'] = 'Der Kontenplan dieser Frage wurde gelöscht. Bitte wählen Sie einen Ersatz und prüfen Sie die Konten vor dem Speichern.';
$string['err_chartrequired'] = 'Bitte wählen Sie einen Kontenplan aus.';
$string['err_creditaccountrequired'] = 'Bitte wählen Sie ein Konto aus oder entfernen Sie diesen Eintrag.';
$string['err_creditamountrequired'] = 'Der Haben-Betrag ist erforderlich.';
$string['err_creditrequired'] = 'Ein Haben-Konto ist erforderlich, wenn ein Soll-Konto angegeben ist.';
$string['err_debitaccountrequired'] = 'Bitte wählen Sie ein Konto aus oder entfernen Sie diesen Eintrag.';
$string['err_debitamountrequired'] = 'Der Soll-Betrag ist erforderlich.';
$string['err_debitrequired'] = 'Ein Soll-Konto ist erforderlich, wenn ein Haben-Konto angegeben ist.';
$string['err_duplicateaccount'] = 'Ein Konto mit diesem Namen ist in diesem Kontenplan bereits vorhanden.';
$string['err_extraentrydeduction_range'] = 'Der Abzug muss zwischen 0 und 100 liegen.';
$string['err_gradeinvalid'] = 'Die Bewertung muss zwischen 0 und 100 liegen.';
$string['err_graderequired'] = 'Die Bewertung ist erforderlich.';
$string['err_gradesumnotcomplete'] = 'Die Summe aller Bewertungen muss genau 100% betragen. Aktuelle Summe: {$a}%';
$string['err_maxentries'] = 'Die maximale Anzahl der Buchungszeilen darf {$a} nicht überschreiten.';
$string['err_minentries'] = 'Die maximale Anzahl der Buchungszeilen muss mindestens 1 sein.';
$string['err_negativeamount'] = 'Beträge müssen positiv sein.';
$string['err_noentries'] = 'Bitte geben Sie mindestens eine Buchungszeile mit Konto und Betrag ein.';
$string['explanation'] = 'Erklärung';
$string['explanation_help'] = 'Eine optionale Erklärung für diesen Buchungssatz, die den Studierenden bei der Überprüfung der richtigen Antwort angezeigt wird.';
$string['exportaccounts'] = 'Konten als CSV exportieren';
$string['extraentrydeduction'] = 'Abzug für überflüssige Konten und Werte';
$string['extraentrydeduction_help'] = 'Prozentualer Abzug pro zusätzlichem Konto, das der Studierende verwendet und nicht in der Musterlösung vorkommt. Jede Seite (Soll/Haben) wird unabhängig gezählt. Auf "Keine" setzen für keinen Abzug. Die Punktzahl kann nicht unter 0 fallen.';
$string['extraentrydeductionpoints'] = 'Abzug für überflüssige Konten (Punkte)';
$string['extraentrydeductionpoints_help'] = 'Punktabzug pro zusätzlichem Konto, das nicht in der Musterlösung vorkommt. Jede Seite (Soll/Haben) wird unabhängig gezählt. Die Punktzahl kann nicht unter 0 fallen.';
$string['extraentrydeductionpoints_option'] = '{$a} Punkt(e) je überflüssigem Konto';
$string['filereaderror'] = 'Fehler beim Lesen der Datei. Bitte versuchen Sie es erneut.';
$string['grade'] = 'Bewertung (%)';
$string['grade_help'] = 'Der Prozentsatz der Gesamtpunktzahl, den dieser Eintrag wert ist. Alle Bewertungen müssen zusammen genau 100% ergeben.';
$string['gradingmode'] = 'Bewertungsart';
$string['gradingmode_help'] = 'Prozent: Konto und Betrag jedes Eintrags werden gewichtet (Gewichte 1-3), das Ergebnis ist ein Prozentsatz der erreichbaren Punkte.

Punkte: Jedes Konto-Betrag-Paar der Musterlösung ist einen Punkt wert. Die erreichbare Punktzahl wird automatisch auf die Anzahl der Paare gesetzt. Je nach Website-Einstellung gibt es den Punkt eines Paares nur, wenn Konto und Betrag richtig sind.';
$string['gradingmode_percent'] = 'Prozent (gewichtet)';
$string['gradingmode_points'] = 'Punkte (ein Punkt je Konto-Betrag-Paar)';
$string['gradingmode_points_info'] = 'Die erreichbare Punktzahl wird beim Speichern aus der Musterlösung berechnet: ein Punkt je Konto-Betrag-Paar. Ist nur das Konto oder nur der Betrag richtig, gibt es einen Teil des Punktes entsprechend der Gewichtung.';
$string['gradingmode_points_info_strict'] = 'Die erreichbare Punktzahl wird beim Speichern aus der Musterlösung berechnet: ein Punkt je Konto-Betrag-Paar. Den Punkt gibt es nur, wenn Konto und Betrag richtig sind; Gewichte werden daher nicht verwendet.';
$string['importaccounts'] = 'Konten aus CSV oder ODS importieren';
$string['importchart'] = 'Kontenplan importieren';
$string['importchartfromcsv'] = 'Kontenplan aus CSV importieren';
$string['importdate'] = 'Importdatum';
$string['imported'] = '{$a} Konten erfolgreich importiert.';
$string['importedchart'] = 'Importierter Kontenplan';
$string['importedchartdesc'] = 'Kontenplan aus CSV-Daten importiert.';
$string['importentries'] = 'Buchungen importieren';
$string['importerror'] = 'Importfehler: ';
$string['importfromcsv'] = 'Aus Excel/CSV importieren';
$string['importhelp'] = 'Laden Sie eine CSV-Datei mit Buchhaltungsdaten hoch. Unterstützte Formate:<br>
<strong>Vollständiges Format:</strong> Sollkonto, Sollkontoname, Sollbetrag, Habenkonto, Habenkontoname, Habenbetrag<br>
<strong>Kompaktes Format:</strong> Sollkonto, Sollbetrag, Habenkonto, Habenbetrag<br>
Verwenden Sie Tab, Semikolon oder Komma als Trennzeichen. Deutsches Zahlenformat (1.234,56) wird unterstützt.';
$string['importlineerror'] = 'Fehler in Zeile {$a}';
$string['importsuccess'] = 'Import erfolgreich! ';
$string['managecharts'] = 'Kontenpläne verwalten';
$string['managechartslink'] = 'Kontenpläne verwalten';
$string['maxentries'] = 'Maximale Anzahl Buchungszeilen';
$string['noaccounts'] = 'Noch keine Konten in diesem Kontenplan.';
$string['noaccountselected'] = 'Kein Konto ausgewählt';
$string['nocharts'] = 'Es wurden noch keine Kontenpläne erstellt.';
$string['nochartselected'] = '-- Kein Kontenplan ausgewählt --';
$string['nocsverror'] = 'Die CSV-Datei ist leer.';
$string['numberformat'] = 'Zahlenformat';
$string['numberformat_de'] = '#.###,00';
$string['numberformat_help'] = 'Wähle das Format für das Dezimal- und das Tausendertrennzeichen. Entweder #.###,00 oder #,###.00';
$string['numberformat_us'] = '#,###.00';
$string['odsinvalid'] = 'Die ODS-Tabelle konnte nicht gelesen werden. Laden Sie eine gültige ODS-Datei mit höchstens 16 MiB unkomprimiertem Inhalt hoch.';
$string['overrideexisting'] = 'Bestehenden Kontenplan überschreiben';
$string['overrideexisting_help'] = 'Wenn aktiviert und ein Kontenplan mit demselben Namen bereits existiert, wird dieser gelöscht und durch den importierten Kontenplan ersetzt. Warnung: Dies löscht den bestehenden Kontenplan und alle seine Konten dauerhaft.';
$string['overrideexistingdesc'] = 'Bestehenden Kontenplan mit gleichem Namen ersetzen';
$string['per'] = 'Per';
$string['pleaseenteranswer'] = 'Bitte geben Sie mindestens einen vollständigen Buchungssatz ein.';
$string['pluginname'] = 'Buchungssatz';
$string['pluginname_help'] = 'Ein Fragetyp, bei dem Studierende Buchungssätze erstellen, indem sie Soll- und Haben-Konten auswählen und Beträge eingeben.';
$string['pluginname_link'] = 'question/type/accounting';
$string['pluginnameadding'] = 'Buchungssatz-Frage hinzufügen';
$string['pluginnameediting'] = 'Buchungssatz-Frage bearbeiten';
$string['pluginnamesummary'] = 'Ein Fragetyp zum Üben von Buchungssätzen. Studierende wählen Konten aus einem Kontenplan und geben Soll-/Haben-Beträge ein.';
$string['privacy:metadata:qtype_accounting_charts'] = 'Die Kontenpläne-Tabelle speichert, wer den jeweiligen Kontenplan zuletzt geändert hat.';
$string['privacy:metadata:qtype_accounting_charts:usermodified'] = 'Die ID des Benutzers, der den Kontenplan zuletzt geändert hat.';
$string['refreshchartsdone'] = 'Kontenpläne aktualisiert.';
$string['refreshchartsfailed'] = 'Die Kontenpläne konnten nicht aktualisiert werden. Bitte versuchen Sie es erneut. Ihre Eingaben wurden beibehalten.';
$string['refreshchartsmissing'] = 'Kontenpläne aktualisiert. Ein ausgewählter Kontenplan oder ein Konto ist nicht mehr verfügbar. Ihre Eingaben wurden beibehalten; bitte wählen Sie vor dem Speichern einen Ersatz.';
$string['refreshentries'] = 'Kontenplan und Zeilen aktualisieren';
$string['selectaccount'] = '-- Konto auswählen --';
$string['selectcreditaccountfirst'] = 'Bitte zuerst ein Habenkonto auswählen';
$string['selectdebitaccountfirst'] = 'Bitte zuerst ein Sollkonto auswählen';
$string['settings_grading'] = 'Bewertung';
$string['sitechart_suffix'] = '{$a} (Website-Standard)';
$string['sitecharts'] = 'Standard-Kontenpläne der Website';
$string['sitecharts_add'] = 'Standard-Kontenplan erstellen oder hochladen';
$string['sitecharts_course_desc'] = 'Diese Kontenpläne stellt die Administration bereit; sie können in jeder Frage ausgewählt werden. Sie lassen sich hier nicht ändern. Kopieren Sie einen Kontenplan in diesen Kurs, um ihn anzupassen.';
$string['sitecharts_desc'] = 'Hier erstellte oder hochgeladene Kontenpläne stehen Lehrkräften in allen Kursen zur Auswahl. Lehrkräfte können weiterhin eigene Kontenpläne in ihren Kursen erstellen oder hochladen.';
$string['strictpairs'] = 'Punkte-Modus: Punkte nur für vollständig richtige Paare';
$string['strictpairs_desc'] = 'Wenn aktiviert, gibt es im Punkte-Modus den Punkt für ein Konto-Betrag-Paar nur, wenn Konto und Betrag richtig sind; sonst gibt es weder für das Konto noch für den Betrag einen Punkt. Wenn deaktiviert, wird der Punkt entsprechend der Gewichtung auf Konto und Betrag aufgeteilt.';
$string['uploadchartcsv'] = 'Kontenplan hochladen (CSV oder ODS)';
$string['uploadchartcsv_btn'] = 'Hochladen';
$string['uploadchartcsv_help'] = 'Laden Sie eine Textdatei hoch, um einen neuen Kontenplan für diesen Kurs zu erstellen. Der Kontenplan erscheint sofort nach dem Hochladen in der Auswahlliste. Format: eine Kontobezeichnung pro Zeile.';
$string['weight'] = 'Gewicht';
$string['weight_help'] = 'Gewichtung für dieses Feld bei der Bewertung (1, 2 oder 3). Höhere Werte bedeuten mehr Punkte. Beispiel: Hat ein Konto das Gewicht 3 und der zugehörige Betrag das Gewicht 1, ist das Konto dreimal so wichtig wie der Betrag für die Bewertung.';
$string['weight_tooltip'] = 'Die Eingabefelder (Konten, Beträge) können mit einem Gewicht von 1–3 versehen werden. Das Gewicht ist für die Berechnung der Teilpunkte relevant. Teilpunkte werden wie folgt berechnet: (Σ Gewichte richtiger Eingabefelder) ÷ (Σ Gewichte aller Eingabefelder) × Erreichbare Punkte.

Wichtig: Konto und Betrag sind verknüpft – ein richtiger Betrag zählt nur, wenn auch das zugehörige Konto korrekt ausgewählt wurde. Ein richtig eingetragenes Konto ohne korrekten Betrag wird hingegen anteilig gewertet.

Die Gewichtung wird überschrieben bzw. hat keinen Effekt, sobald die Option „Keine Teilpunkte vergeben" aktiviert ist.';
$string['witherrors'] = 'mit Fehlern';
