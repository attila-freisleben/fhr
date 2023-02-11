<?php


$sp_key = $key;
if ($key == "code")
    $sp_key = "code.coding.code";


function sp_Patient($key)
{
    $sp_key = $key;

    if ($key == "address")
        $sp_key = "address.text";

    if ($key == "address-city")
        $sp_key = "address.city";

    if ($key == "address-country")
        $sp_key = "address.country";

    if ($key == "address-postalcode")
        $sp_key = "address.postalCode";

    if ($key == "address-state")
        $sp_key = "address.state";

    if ($key == "address-use")
        $sp_key = "address.use";

    if ($key == "birthdate")
        $sp_key = "birthDate";

    if ($key == "death-date")
        $sp_key = "deceased";

    if ($key == "deceased")
        $sp_key = "deceased";

    if ($key == "email")
        $sp_key = "telecom";              //--------------

    if ($key == "family")
        $sp_key = "name.family";

    if ($key == "general-practitioner")
        $sp_key = "generalPractitioner";

    if ($key == "given")
        $sp_key = "name.given";

    if ($key == "language")
        $sp_key = "communication[*].language";

    if ($key == "link")
        $sp_key = "link.other";

    if ($key == "name")
        $sp_key = "name.text";

    if ($key == "organization")
        $sp_key = "managingOrganization";

    if ($key == "phone")          //------------------
        $sp_key = "telecom";

    if ($key == "phonetic")
        $sp_key = "name.text";


    return ($sp_key);

}


/***************************************/
function sp_Encounter($key)
{
    /***************************************/
    $sp_key = $key;
    if ($key == "based-on")
        $sp_key = "basedOn";

    if ($key == "date")
        $sp_key = "period.start";

    if ($key == "diagnosis")
        $sp_key = "diagnosis.condition";

    if ($key == "episode-of-care")
        $sp_key = "episodeOfCare";

    if ($key == "location")
        $sp_key = "location.location";

    if ($key == "location-period")
        $sp_key = "location.period";

    if ($key == "part-of")
        $sp_key = "partOf";

    if ($key == "participant")
        $sp_key = "participant.individual";

    if ($key == "participant-type")
        $sp_key = "participant.type";

    if ($key == "patient")
        $sp_key = "subject";

    if ($key == "practitioner")
        $sp_key = "participant.individual";

    if ($key == "reason-code")
        $sp_key = "reason.code";

    if ($key == "reason-reference")
        $sp_key = "reason.reference";

    if ($key == "service-provider")
        $sp_key = "serviceProvider";

    if ($key == "special-arrangement")
        $sp_key = "hospitalization.specialArrangement";

    return ($sp_key);
}

/************************* MAIN ******************************/

foreach ($_vars as $key => $arr) {
    foreach ($arr as $ind => $val) {

        $keys = explode(".", $key);
        foreach ($keys as $kk) {
            $sp_key = sp_Patient($kk);
            $sp_key = sp_Encounter($kk);
        }

        $_vars2[$sp_key][$ind] = $val;
    }
}

?>