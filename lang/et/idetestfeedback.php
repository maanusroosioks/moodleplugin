<?php
// This file is part of Moodle - http://moodle.org/

$string['pluginname']            = 'IDE Test Feedback';
$string['modulename']            = 'IDE Test Feedback';
$string['modulenameplural']      = 'IDE Test Feedback tegevused';
$string['modulename_help']       = 'IDE Test Feedback tegevus võtab vastu üksustestide tulemusi, mis on saadetud õpilase IDE-st, ja kuvab need ülesande kaupa.';
$string['activityname']          = 'Tegevuse nimi';
$string['pluginadministration']  = 'IDE Test Feedback haldus';

// Assignment key
$string['assignmentkey']           = 'Ülesande võti';
$string['assignmentkey_help']      = 'Kopeeri see võti oma IDE plugina seadetesse, et linkida esitused selle tegevusega.';
$string['assignmentkey_generated'] = 'Salvestamisel genereeritakse automaatselt unikaalne võti.';

// Defined test cases
$string['requiredtestsheading'] = 'Määratud testijuhtumid';
$string['requiredtests']        = 'Arvesse minevad testijuhtumid';
$string['requiredtests_help']   = 'Loetle soovi korral testijuhtumid, mis selle tegevuse jaoks arvesse lähevad, üks real, kujul `testName` või `testSuite#testName`.

Kui see on täidetud, hinnatakse iga jooksu selle loendi alusel: jooksust puuduvad, ebaõnnestunud või vahele jäetud määratud testid näidatakse jooksu üksikasjades ning "läbiv testijooks" lõpetamisreegel nõuab jooksu, mis läbib kõik määratud testid.

Jäta tühjaks, et arvesse võtta kõik IDE poolt saadetud testid (siis lõpetab tegevuse iga jooks, mille üldine staatus on PASSED).

Sobitamine ei arvesta tähesuurust; sisesta nimed nii, nagu need on jooksu üksikasjade lehel. Tulemused on endiselt õpilase keskkonnast esitatud ega ole sõltumatult kontrollitud.';
$string['requiredprogress']    = 'Läbitud määratud testid';
$string['requiredoutstanding'] = 'Lahendamata määratud testid';
$string['requiredfailedn']     = '{$a} ebaõnnestunud';
$string['requiredskippedn']    = '{$a} vahele jäetud';
$string['requiredmissingn']    = '{$a} esitamata';

// Submission window
$string['submissionwindow']      = 'Esitamise ajavaken';
$string['timeopen']              = 'Avatud alates';
$string['timeclose']             = 'Suletud alates';
$string['submissionnotopen']     = 'Esitamine avaneb {$a}.';
$string['submissionclosed']      = 'Esitamine suleti {$a}.';
$string['error_closebeforeopen'] = 'Sulgemiskuupäev peab olema pärast avamiskuupäeva.';

// Validation error messages
$string['idetestfeedback_validation_usernotfound']       = 'E-postiga "{$a}" aktiivset kasutajat ei leitud.';
$string['idetestfeedback_validation_assignmentnotfound'] = 'Ülesande võtmele "{$a}" vastavat tegevust ei leitud.';
$string['idetestfeedback_validation_notenrolled']        = 'Kasutaja ei ole selle tegevuse kursusele registreeritud.';
$string['idetestfeedback_validation_windownotopen']      = 'Selle tegevuse esitamise ajaaken avaneb {$a}.';
$string['idetestfeedback_validation_windowclosed']       = 'Selle tegevuse esitamise ajaaken sulgus {$a}.';
$string['idetestfeedback_validation_invalidstatus']      = 'Staatuse väärtus "{$a}" ei ole lubatud. Oodatud üks järgnevatest: PASSED, FAILED, SKIPPED, ERROR.';
$string['idetestfeedback_validation_noresults']          = 'Selle testijooksuga ei esitatud ühtegi tulemust.';

// Headings
$string['viewresults'] = 'Kõik testitulemused';
$string['myresults']   = 'Minu testitulemused';
$string['rundetail']   = 'Testijooksu üksikasjad';
$string['testresults'] = 'Üksikud testitulemused';
$string['back']        = 'Tagasi';

// Table columns
$string['student']     = 'Õppija';
$string['ide']         = 'IDE';
$string['projectname'] = 'Projekt';
$string['commithash']  = 'Commit hash';
$string['status']      = 'Staatus';
$string['passed']      = 'Läbitud';
$string['failed']      = 'Ebaõnnestunud';
$string['skipped']     = 'Vahele jäetud';
$string['timecreated'] = 'Kuupäev';
$string['testsuite']   = 'Testikomplekt';
$string['testname']    = 'Testi nimi';
$string['required']    = 'Nõutud';
$string['duration']    = 'Kestus';
$string['message']     = 'Veateade';
$string['viewdetail']  = 'Vaata';

// Filters
$string['allstudents']   = 'Kõik õppijad';
$string['allstatuses']   = 'Kõik staatused';

// Dynamic messages
$string['noresults']      = 'Testitulemusi ei leitud.';
$string['nomatchingruns'] = 'Valitud filtritele ei vasta ükski testijooks.';
$string['runnotfound']    = 'Testijooksu ei leitud.';
$string['totalruns']      = 'Kokku: {$a} testijooksu';
$string['summarytext'] = 'Sul on selle ülesande jaoks {$a->total} testijooksu. Läbimise määr: {$a->rate}%.';

// Events
$string['event_test_run_submitted'] = 'Testijooks esitatud';

// Completion
$string['completionpassrun']      = 'Õpilane peab esitama läbiva testijooksu';
$string['completionpassrun_desc'] = 'Esita testijooks, kus ei ole ühtegi ebaõnnestunud ega vigast testi. Kui tegevusele on määratud testijuhtumid, peab jooks läbima kõik määratud testid.';

// Capabilities
$string['idetestfeedback:view']          = 'Vaata enda IDE testitulemusi';
$string['idetestfeedback:viewall']       = 'Vaata kõigi õpilaste IDE testitulemusi';
$string['idetestfeedback:submit']        = 'Saada IDE testitulemusi API kaudu';
$string['idetestfeedback:addinstance']   = 'Lisa uus IDE Test Feedback tegevus';

// Privacy
$string['privacy:metadata:run']             = 'Salvestab IDE testijooksu esitused.';
$string['privacy:metadata:run:userid']      = 'Kasutaja, kes testijooksu esitas.';
$string['privacy:metadata:run:ide']         = 'Testide käivitamiseks kasutatud IDE.';
$string['privacy:metadata:run:projectname'] = 'Projekti nimi esitamise hetkel.';
$string['privacy:metadata:run:commithash']  = 'Git commit hash esitamise hetkel.';
$string['privacy:metadata:run:startedat']    = 'Millal testijooks algas.';
$string['privacy:metadata:run:finishedat']   = 'Millal testijooks lõppes.';
$string['privacy:metadata:run:status']      = 'Üldine jooksu staatus (PASSED, FAILED, ERROR, SKIPPED).';
$string['privacy:metadata:run:passedcount']  = 'Jooksus läbitud testijuhtumite arv.';
$string['privacy:metadata:run:failedcount']  = 'Jooksus ebaõnnestunud testijuhtumite arv.';
$string['privacy:metadata:run:skippedcount'] = 'Jooksus vahele jäetud testijuhtumite arv.';
$string['privacy:metadata:run:errorcount']   = 'Jooksus vigadega testijuhtumite arv.';
$string['privacy:metadata:run:timecreated'] = 'Millal jooks esitati.';
$string['privacy:metadata:result']          = 'Salvestab üksikud testijuhtumi tulemused jooksu sees.';
$string['privacy:metadata:result:testsuite']      = 'Testikomplekt, kuhu testijuhtum kuulub.';
$string['privacy:metadata:result:testname']       = 'Testijuhtumi nimi.';
$string['privacy:metadata:result:status']         = 'Testijuhtumi tulemus.';
$string['privacy:metadata:result:durationms']     = 'Testijuhtumi kestus millisekundites.';
$string['privacy:metadata:result:message']        = 'Ebaõnnestumise või vea teade.';
$string['privacy:metadata:result:stacktracehash'] = 'Vea pinujälje räsi, mida kasutatakse sarnaste vigade rühmitamiseks.';
$string['privacy:metadata:result:timecreated']    = 'Millal testijuhtumi tulemus salvestati.';
