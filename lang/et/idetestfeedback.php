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
 * Estonian strings for mod_idetestfeedback.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['activityname'] = 'Tegevuse nimi';
$string['allstatuses'] = 'Kõik staatused';
$string['allstudents'] = 'Kõik õppijad';
$string['assignmentkey'] = 'Ülesande võti';
$string['assignmentkey_generated'] = 'Salvestamisel genereeritakse automaatselt unikaalne võti.';
$string['assignmentkey_help'] = 'Kopeeri see võti oma IDE plugina seadetesse, et linkida esitused selle tegevusega.';
$string['back'] = 'Tagasi';
$string['closebeforeopen'] = 'Sulgemiskuupäev peab olema pärast avamiskuupäeva.';
$string['commithash'] = 'Commit hash';
$string['completionpassrun'] = 'Õpilane peab esitama läbiva testijooksu';
$string['completionpassrun_desc'] = 'Esita testijooks, kus kõik testid läbiti. Ebaõnnestunud, vigased ja vahele jäetud testid jätavad tegevuse lõpetamata. Kui tegevusele on määratud testijuhtumid, peavad läbima ainult need.';
$string['duration'] = 'Kestus';
$string['durationunit'] = '{$a} ms';
$string['event_test_run_submitted'] = 'Testijooks esitatud';
$string['failed'] = 'Ebaõnnestunud';
$string['feedback'] = 'Õpetaja tagasiside';
$string['feedbackfor'] = 'Tagasiside testile {$a}';
$string['feedbackmsgintro'] = 'Sinu õpetaja jättis sinu testijooksule tagasiside:';
$string['feedbackmsgsmall'] = 'Sinu õpetaja jättis sinu IDE testitulemustele tagasiside.';
$string['feedbackmsgsubject'] = 'Uus tagasiside sinu testitulemustele tegevuses {$a}';
$string['feedbacksaved'] = 'Tagasiside salvestatud.';
$string['feedbacksavednotified'] = 'Tagasiside salvestatud. Õppijat on teavitatud.';
$string['ide'] = 'IDE';
$string['idetestfeedback:addinstance'] = 'Lisa uus IDE Test Feedback tegevus';
$string['idetestfeedback:comment'] = 'Lisa õppijate testitulemustele tagasisidet';
$string['idetestfeedback:submit'] = 'Saada IDE testitulemusi API kaudu';
$string['idetestfeedback:view'] = 'Vaata enda IDE testitulemusi';
$string['idetestfeedback:viewall'] = 'Vaata kõigi õpilaste IDE testitulemusi';
$string['message'] = 'Veateade';
$string['messageprovider:feedback'] = 'Tagasiside sinu IDE testitulemustele';
$string['modulename'] = 'IDE Test Feedback';
$string['modulename_help'] = 'IDE Test Feedback tegevus võtab vastu üksustestide tulemusi, mis on saadetud õpilase IDE-st, ja kuvab need ülesande kaupa.';
$string['modulenameplural'] = 'IDE Test Feedback tegevused';
$string['myresults'] = 'Minu testitulemused';
$string['nomatchingruns'] = 'Valitud filtritele ei vasta ükski testijooks.';
$string['noresults'] = 'Testitulemusi ei leitud.';
$string['notifystudent'] = 'Teavita õppijat sõnumiga';
$string['passed'] = 'Läbitud';
$string['pluginadministration'] = 'IDE Test Feedback haldus';
$string['pluginname'] = 'IDE Test Feedback';
$string['privacy:metadata:messages'] = 'Õppijatele saadetakse teavitus, kui õpetaja jätab nende testijooksule tagasisidet.';
$string['privacy:metadata:result'] = 'Salvestab üksikud testijuhtumi tulemused jooksu sees.';
$string['privacy:metadata:result:durationms'] = 'Testijuhtumi kestus millisekundites.';
$string['privacy:metadata:result:feedback'] = 'Tagasiside, mille õpetaja testijuhtumi tulemusele kirjutas.';
$string['privacy:metadata:result:feedbackby'] = 'Õpetaja, kes tagasiside kirjutas.';
$string['privacy:metadata:result:feedbackformat'] = 'Tekstivorming, milles tagasiside on salvestatud.';
$string['privacy:metadata:result:feedbackmodified'] = 'Millal tagasisidet viimati muudeti.';
$string['privacy:metadata:result:message'] = 'Ebaõnnestumise või vea teade.';
$string['privacy:metadata:result:stacktracehash'] = 'Vea pinujälje räsi, mida kasutatakse sarnaste vigade rühmitamiseks.';
$string['privacy:metadata:result:status'] = 'Testijuhtumi tulemus.';
$string['privacy:metadata:result:testname'] = 'Testijuhtumi nimi.';
$string['privacy:metadata:result:testsuite'] = 'Testikomplekt, kuhu testijuhtum kuulub.';
$string['privacy:metadata:result:timecreated'] = 'Millal testijuhtumi tulemus salvestati.';
$string['privacy:metadata:run'] = 'Salvestab IDE testijooksu esitused.';
$string['privacy:metadata:run:commithash'] = 'Git commit hash esitamise hetkel.';
$string['privacy:metadata:run:errorcount'] = 'Jooksus vigadega testijuhtumite arv.';
$string['privacy:metadata:run:failedcount'] = 'Jooksus ebaõnnestunud testijuhtumite arv.';
$string['privacy:metadata:run:finishedat'] = 'Millal testijooks lõppes.';
$string['privacy:metadata:run:ide'] = 'Testide käivitamiseks kasutatud IDE.';
$string['privacy:metadata:run:passedcount'] = 'Jooksus läbitud testijuhtumite arv.';
$string['privacy:metadata:run:projectname'] = 'Projekti nimi esitamise hetkel.';
$string['privacy:metadata:run:skippedcount'] = 'Jooksus vahele jäetud testijuhtumite arv.';
$string['privacy:metadata:run:startedat'] = 'Millal testijooks algas.';
$string['privacy:metadata:run:status'] = 'Üldine jooksu staatus (PASSED, FAILED, ERROR, SKIPPED).';
$string['privacy:metadata:run:timecreated'] = 'Millal jooks esitati.';
$string['privacy:metadata:run:userid'] = 'Kasutaja, kes testijooksu esitas.';
$string['privacy:path:feedbackgiven'] = 'Teistele õpilastele antud tagasiside';
$string['privacy:path:runs'] = 'Testijooksud';
$string['projectname'] = 'Projekt';
$string['required'] = 'Nõutud';
$string['requiredfailedn'] = '{$a} ebaõnnestunud';
$string['requiredmissingn'] = '{$a} esitamata';
$string['requiredoutstanding'] = 'Lahendamata määratud testid';
$string['requiredprogress'] = 'Läbitud määratud testid';
$string['requiredskippedn'] = '{$a} vahele jäetud';
$string['requiredtests'] = 'Arvesse minevad testijuhtumid';
$string['requiredtests_help'] = 'Loetle soovi korral testijuhtumid, mis selle tegevuse jaoks arvesse lähevad, üks real, kujul `testName` või `testSuite#testName`.

Kui see on täidetud, hinnatakse iga jooksu selle loendi alusel: jooksust puuduvad, ebaõnnestunud või vahele jäetud määratud testid näidatakse jooksu üksikasjades ning "läbiv testijooks" lõpetamisreegel nõuab jooksu, mis läbib kõik määratud testid.

Jäta tühjaks, et arvesse võtta kõik IDE poolt saadetud testid. Lõpetamisreegel nõuab siis jooksu, kus kõik esitatud testid läbiti, nii et ka vahele jäetud testidega jooks ei lõpeta tegevust.

Sobitamine ei arvesta tähesuurust; sisesta nimed nii, nagu need on jooksu üksikasjade lehel. Tulemused on endiselt õpilase keskkonnast esitatud ega ole sõltumatult kontrollitud.';
$string['requiredtestsheading'] = 'Määratud testijuhtumid';
$string['resetruns'] = 'Kustuta kõik esitatud testijooksud';
$string['rundetail'] = 'Testijooksu üksikasjad';
$string['runnotfound'] = 'Testijooksu ei leitud.';
$string['savefeedback'] = 'Salvesta tagasiside';
$string['skipped'] = 'Vahele jäetud';
$string['status'] = 'Staatus';
$string['student'] = 'Õppija';
$string['submissionclosed'] = 'Esitamine suleti {$a}.';
$string['submissionnotopen'] = 'Esitamine avaneb {$a}.';
$string['submissionwindow'] = 'Esitamise ajavaken';
$string['summarytext'] = 'Sul on selle ülesande jaoks {$a->total} testijooksu. Läbimise määr: {$a->rate}%.';
$string['testname'] = 'Testi nimi';
$string['testresults'] = 'Üksikud testitulemused';
$string['testsuite'] = 'Testikomplekt';
$string['timeclose'] = 'Suletud alates';
$string['timecreated'] = 'Kuupäev';
$string['timeopen'] = 'Avatud alates';
$string['totalruns'] = 'Kokku: {$a} testijooksu';
$string['validationambiguousemail'] = 'E-postiga "{$a}" leiti rohkem kui üks aktiivne kasutaja.';
$string['validationassignmentnotfound'] = 'Ülesande võtmele "{$a}" vastavat tegevust ei leitud.';
$string['validationinvalidstatus'] = 'Staatuse väärtus "{$a}" ei ole lubatud. Oodatud üks järgnevatest: PASSED, FAILED, SKIPPED, ERROR.';
$string['validationinvalidtiming'] = 'Esitatud ajad ei ole lubatud. Kestus ei saa olla negatiivne ja jooks ei saa lõppeda enne alustamist.';
$string['validationnoide'] = 'Selle testijooksuga ei esitatud IDE tunnust.';
$string['validationnoresults'] = 'Selle testijooksuga ei esitatud ühtegi tulemust.';
$string['validationnotenrolled'] = 'Kasutaja ei ole selle tegevuse kursusele registreeritud.';
$string['validationtoomanyresults'] = 'Selle testijooksuga esitati liiga palju tulemusi. Lubatud on kuni {$a}.';
$string['validationusernotfound'] = 'E-postiga "{$a}" aktiivset kasutajat ei leitud.';
$string['validationwindowclosed'] = 'Selle tegevuse esitamise ajaaken sulgus {$a}.';
$string['validationwindownotopen'] = 'Selle tegevuse esitamise ajaaken avaneb {$a}.';
$string['viewdetail'] = 'Vaata';
$string['viewresults'] = 'Kõik testitulemused';
