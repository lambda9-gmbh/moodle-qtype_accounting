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
$string['accountdeleted'] = 'Konto erfolgreich gelöscht.';
$string['accountlist'] = 'Konten dieser Frage';
$string['accountlist_help'] = 'Geben Sie die Konten ein, aus denen Studierende wählen können, eins pro Zeile, zum Beispiel <code>1200 Bank</code>. Die Frage behält eine eigene Kopie dieser Konten: Spätere Änderungen an Vorlagen oder anderen Fragen wirken sich nicht auf sie aus. Wird ein Konto in einer neuen Version umbenannt oder entfernt, lassen sich Versuche früherer Versionen nicht mit ihr neu bewerten.';
$string['accountname'] = 'Kontobezeichnung';
$string['accountnumber'] = 'Kontobezeichnung';
$string['accounts'] = 'Konten';
$string['accountsindropdown'] = 'Anzahl zusätzlicher Konten in der Auswahlliste';
$string['accountsindropdown_help'] = 'Die Anzahl der zufälligen zusätzlichen Konten, die neben dem richtigen Konto in der Auswahlliste angezeigt werden sollen. Alle umfasst sämtliche Konten des ausgewählten Kontenplans. Ein leeres Feld oder die Eingabe von 0 wählt ebenfalls Alle. Beispiel: Bei Eingabe von 3 wird das richtige Konto plus 3 zufällige Konten angezeigt (4 insgesamt).';
$string['accountsonlyexception'] = 'Punkte-Modus: ein Punkt für richtige Konten ohne Beträge';
$string['accountsonlyexception_desc'] = 'Wenn aktiviert, erhalten Schülerinnen und Schüler im Punkte-Modus einen Punkt, wenn sie auf beiden Seiten genau die richtigen Konten nennen (keins fehlt, keins zu viel), aber keine Beträge eintragen – statt keinem Punkt, wenn nur vollständig richtige Paare zählen. Die Musterlösung der Lehrkraft enthält immer Konten und Beträge.';
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
$string['amountminwidth'] = 'Mindestbreite der Betragsfelder';
$string['amountminwidth_desc'] = 'Mindestbreite der Eingabefelder für Beträge in der Buchungstabelle, in Pixeln.';
$string['an'] = 'an';
$string['autocalculatedfromfractions'] = 'Automatisch berechnet aus der Summe der Punktzahlen';
$string['balanced'] = 'Ausgeglichen';
$string['chartcreated'] = 'Kontenplan erfolgreich erstellt.';
$string['chartdeleted'] = 'Kontenplan erfolgreich gelöscht.';
$string['chartdescription'] = 'Beschreibung';
$string['chartexists_enableoverride'] = 'Ein Kontenplan mit dem Namen "{$a}" existiert bereits. Aktivieren Sie "Bestehenden Kontenplan überschreiben", um ihn zu ersetzen.';
$string['chartimportfailed'] = 'Kontenplan-Import fehlgeschlagen';
$string['chartimportsuccess'] = '{$a} Konten erfolgreich importiert';
$string['chartname'] = 'Name des Kontenplans';
$string['chartnotfound'] = 'Kontenplan nicht gefunden.';
$string['chartofaccounts_section'] = 'Kontenplan';
$string['confirmdelete'] = 'Möchten Sie die Kontenplan-Vorlage „{$a}“ wirklich endgültig löschen? Alle Konten dieser Vorlage werden ebenfalls gelöscht. Fragen, die ihre Konten übernommen haben, behalten ihre eigene Kopie. Dieser Vorgang kann nicht rückgängig gemacht werden.';
$string['confirmdeleteaccount'] = 'Sind Sie sicher, dass Sie dieses Konto löschen möchten?';
$string['copyaccounts'] = 'Übernehmen';
$string['copyaccounts_questions'] = 'Fragen dieser Fragensammlung';
$string['copyaccounts_templates'] = 'Website-Vorlagen';
$string['copyaccountsfrom'] = 'Konten übernehmen aus';
$string['copyaccountsfrom_help'] = 'Ersetzt die Kontenliste oben durch die Konten einer Website-Vorlage oder einer anderen Frage dieser Fragensammlung. Die Konten werden kopiert; die Frage bleibt nicht mit der Quelle verbunden.';
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
$string['currency'] = 'Währung';
$string['currency_chf'] = 'CHF (Schweizer Franken)';
$string['currency_eur'] = '€ (Euro)';
$string['currency_gbp'] = '£ (Britisches Pfund)';
$string['currency_help'] = 'Das Währungssymbol, das hinter den Betragsfeldern angezeigt wird. Wähle „Kein Symbol“, um nur die Beträge anzuzeigen.';
$string['currency_none'] = 'Kein Symbol';
$string['currency_usd'] = '$ (US-Dollar)';
$string['debit'] = 'Soll';
$string['debitaccount'] = 'Sollkonto';
$string['debitamount'] = 'Sollbetrag';
$string['debitentries'] = 'Sollbuchungen';
$string['debithasextraaccounts'] = 'Die Soll-Seite enthält überflüssige Konten.';
$string['debitincorrect'] = 'Die Soll-Seite ist nicht korrekt.';
$string['debitpartiallyincorrect'] = 'Die Soll-Seite ist teilweise nicht korrekt.';
$string['defaultcurrency'] = 'Standardwährung';
$string['defaultcurrency_desc'] = 'Währung, die für neue Fragen vorausgewählt ist. Ihr Symbol wird auch bei bestehenden Fragen ohne gespeicherte Währung angezeigt.';
$string['defaultgradingmode'] = 'Standard-Bewertungsart';
$string['defaultgradingmode_desc'] = 'Bewertungsart, die bei neuen Buchungssatz-Fragen vorausgewählt ist. Lehrkräfte können sie je Frage ändern.';
$string['deleteaccount'] = 'Konto löschen';
$string['deletechart'] = 'Kontenplan löschen';
$string['deletecredit'] = 'Haben-Buchung löschen';
$string['deletedebit'] = 'Soll-Buchung löschen';
$string['deleteentry'] = 'Löschen';
$string['distributegradesequally'] = 'Gleichmäßig verteilen';
$string['editaccountname'] = 'Kontonamen bearbeiten';
$string['editaccounts'] = 'Konten bearbeiten';
$string['editchart'] = 'Kontenplan bearbeiten';
$string['editchartname'] = 'Namen des Kontenplans bearbeiten';
$string['enteraccount'] = 'Kontonummer eingeben';
$string['entriesimported'] = 'Buchungen importiert.';
$string['entry'] = 'Buchungszeile {no}';
$string['entryno'] = 'Buchung {no}';
$string['err_accountduplicate'] = 'Das Konto „{$a}“ steht mehrfach in der Liste.';
$string['err_accountnametoolong'] = 'Kontonamen dürfen höchstens {$a} Zeichen lang sein.';
$string['err_accountsindropdown'] = 'Geben Sie Alle oder eine nicht negative ganze Zahl ein.';
$string['err_accountsindropdown_negative'] = 'Die Anzahl der Konten in der Auswahlliste darf nicht negativ sein.';
$string['err_accountunavailable'] = 'Dieses Konto steht nicht mehr in der Kontenliste. Bitte wählen Sie ein anderes Konto.';
$string['err_balancemismatch'] = 'Die Summe der Soll-Beträge muss der Summe der Haben-Beträge entsprechen.';
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
$string['event_chart_created'] = 'Kontenplan-Vorlage angelegt';
$string['event_chart_deleted'] = 'Kontenplan-Vorlage gelöscht';
$string['event_chart_updated'] = 'Kontenplan-Vorlage geändert';
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
$string['maxentries'] = 'Maximale Anzahl Buchungszeilen';
$string['newaccountname'] = 'Neuer Name für Konto {$a}';
$string['newchartname'] = 'Neuer Name für Kontenplan {$a}';
$string['noaccounts'] = 'Noch keine Konten in diesem Kontenplan.';
$string['noaccountselected'] = 'Kein Konto ausgewählt';
$string['nocharts'] = 'Es wurden noch keine Kontenpläne erstellt.';
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
$string['regradeaccountschanged'] = 'Die Konten dieser Frage unterscheiden sich zwischen den Versionen, daher können Versuche nicht mit der anderen Version neu bewertet werden.';
$string['selectaccount'] = '-- Konto auswählen --';
$string['settings_display'] = 'Darstellung';
$string['settings_grading'] = 'Bewertung';
$string['showaccountsindropdown'] = 'Option „Anzahl zusätzlicher Konten in der Auswahlliste“ anzeigen';
$string['showaccountsindropdown_desc'] = 'Bietet im Frageeditor die Option „Anzahl zusätzlicher Konten in der Auswahlliste“ an. Wenn deaktiviert, wird die Option ausgeblendet: Neue Fragen zeigen den gesamten Kontenrahmen, bestehende Fragen behalten ihren gespeicherten Wert.';
$string['showpercolumn'] = 'Spalte „Per“ anzeigen';
$string['showpercolumn_desc'] = 'Zeigt die erste Beschriftungsspalte („Per“) der Buchungstabelle für Schülerinnen und Schüler und im Frageeditor an. Wenn deaktiviert, wird die Spalte ausgeblendet.';
$string['sitecharts'] = 'Kontenplan-Vorlagen';
$string['sitecharts_add'] = 'Kontenplan-Vorlage erstellen oder hochladen';
$string['sitecharts_desc'] = 'Lehrkräfte können die Konten dieser Vorlagen in ihre Fragen übernehmen. Jede Frage behält eine eigene Kopie; Ändern oder Löschen einer Vorlage wirkt sich daher nicht auf bestehende Fragen aus.';
$string['strictpairs'] = 'Punkte-Modus: Punkte nur für vollständig richtige Paare';
$string['strictpairs_desc'] = 'Wenn aktiviert, gibt es im Punkte-Modus den Punkt für ein Konto-Betrag-Paar nur, wenn Konto und Betrag richtig sind; sonst gibt es weder für das Konto noch für den Betrag einen Punkt. Wenn deaktiviert, wird der Punkt entsprechend der Gewichtung auf Konto und Betrag aufgeteilt.';
$string['templatechangesinfo'] = 'Fragen behalten eine eigene Kopie der Konten. Änderungen an dieser Vorlage wirken sich nur auf Konten aus, die später in Fragen übernommen werden.';
$string['uploadchartcsv'] = 'Kontenplan hochladen (CSV oder ODS)';
$string['uploadchartcsv_btn'] = 'Hochladen';
$string['uploadchartcsv_help'] = 'Laden Sie eine Textdatei hoch, um einen neuen Kontenplan für diese Fragensammlung zu erstellen. Der Kontenplan erscheint sofort nach dem Hochladen in der Auswahlliste. Format: eine Kontobezeichnung pro Zeile.';
$string['weight'] = 'Gewicht';
$string['weight_help'] = 'Gewichtung für dieses Feld bei der Bewertung (1, 2 oder 3). Höhere Werte bedeuten mehr Punkte. Beispiel: Hat ein Konto das Gewicht 3 und der zugehörige Betrag das Gewicht 1, ist das Konto dreimal so wichtig wie der Betrag für die Bewertung.';
$string['witherrors'] = 'mit Fehlern';
