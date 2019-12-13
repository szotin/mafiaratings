function initScoringEditor(scoringStr)
{
    var scoring = null;
    try
    {
        scoring = JSON.parse(scoringStr);
    }
    catch (error)
    {
        console.log(error);
        scoring = JSON.parse("{}");
    }
    $("#result").html(JSON.stringify(scoring));
}
