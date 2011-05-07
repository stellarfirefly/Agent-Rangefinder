<head><title>Agent Rangefinder</title></head>
<link rel="stylesheet" href="../../igb.basic.css">
<link rel="stylesheet" href="../../chruker.default.css">

<script language="javascript" type="text/javascript">
<!-- Warn if CCPEVE.showInfo() not available.
function ARF_showInfo(iType,itemID){
	if(typeof CCPEVE == 'object'){
		CCPEVE.showInfo(iType,itemID);
	}else{
		alert("Show Info is only supported when using the In-Game Browser.");
	}
}
//-->
</script>

<h2>Turhan Bey's Agent Rangefinder 1.1</h2>
<hr>

<?php
$bDebug=false;
require_once "../../errorsToBrowser.php";
require_once('../../OfficialDatabase.info.php');
require_once('../../CCPSystems.lib.php');
require_once('../../CCPAgents.lib.php');

//-------- global variables
$sSelf=$_SERVER["PHP_SELF"];
$MAX_JUMPRANGE=12;

//-------- Debug Output Function
function DebugOut($sMsg)
{
	GLOBAL $bDebug;

	if($bDebug) print "<h4>DEBUG: $sMsg</h4>";
}

//-------- Retrieve form values or defaults
$sSystem=isset($_POST['System'])?$_POST['System']:"";
$iJumpRange=isset($_POST['JumpRange'])?$_POST['JumpRange']:0;
$bAvoidLowsec=isset($_POST['AvoidLowsec'])?$_POST['AvoidLowsec']:false;
$bShowStoryline=isset($_POST['agtStoryline'])?$_POST['agtStoryline']:false;
$bShowLevel_1=isset($_POST['agtLevel_1'])?$_POST['agtLevel_1']:false;
$bShowLevel_2=isset($_POST['agtLevel_2'])?$_POST['agtLevel_2']:false;
$bShowLevel_3=isset($_POST['agtLevel_3'])?$_POST['agtLevel_3']:false;
$bShowLevel_4=isset($_POST['agtLevel_4'])?$_POST['agtLevel_4']:false;
$bShowLevel_5=isset($_POST['agtLevel_5'])?$_POST['agtLevel_5']:false;

//-------- Display Report Request Form
$sAvoidLowsec="";
$sStoryline="";
$sLevel1="";
$sLevel2="";
$sLevel3="";
$sLevel4="";
$sLevel5="";
if($bAvoidLowsec){$sAvoidLowsec="checked";}
if($bShowStoryline){$sStoryline="checked";}
if($bShowLevel_1){$sLevel1="checked";}
if($bShowLevel_2){$sLevel2="checked";}
if($bShowLevel_3){$sLevel3="checked";}
if($bShowLevel_4){$sLevel4="checked";}
if($bShowLevel_5){$sLevel5="checked";}
print <<<REPORT_FORM
<form action="$sSelf" method="post">
<p>
<table>
<tr><td>System Name:</td><td><input type="text" name="System" value="$sSystem" /></td></tr>
<tr><td>Jump Range:</td><td><input type="text" name="JumpRange" value="$iJumpRange" /></td></tr>
<tr><td></td><td><input type="checkbox" name="AvoidLowsec" value="true" $sAvoidLowsec />Avoid LowSec</td></tr>
<tr><td><input type="checkbox" name="agtStoryline" value="true" $sStoryline />Storyline</td></tr>
<tr><td><input type="checkbox" name="agtLevel_1" value="true" $sLevel1 />Level 1</td></tr>
<tr><td><input type="checkbox" name="agtLevel_2" value="true" $sLevel2 />Level 2</td></tr>
<tr><td><input type="checkbox" name="agtLevel_3" value="true" $sLevel3 />Level 3</td></tr>
<tr><td><input type="checkbox" name="agtLevel_4" value="true" $sLevel4 />Level 4</td></tr>
<tr><td><input type="checkbox" name="agtLevel_5" value="true" $sLevel5 />Level 5</td></tr>
</table>
<p /><input type="submit" id="submit" name="submit" value="Find Agents" />
</form><hr /><p />
REPORT_FORM;

if(isset($_POST['submit'])){
	$oSystems=new CCPSystems($sCCPHost,$sCCPLogin,$sCCPPassword,$sCCPSchema);
	$oAgents=new CCPAgents($sCCPHost,$sCCPLogin,$sCCPPassword,$sCCPSchema);
	$hDB=new mysqli($sCCPHost,$sCCPLogin,$sCCPPassword,$sCCPSchema);

	// limit check
	if($iJumpRange<0 || $iJumpRange>$MAX_JUMPRANGE){
		InternalError("Jump Range must be from zero to $MAX_JUMPRANGE.");
		return false;
	}

	// obtain system list
	if(!$oSystems){
		InternalError("Initialization of CCPSystems module failed: ".$oSystems->error);
		return false;
	}
	$hStartSystems=$oSystems->FindSystemExact("$sSystem");
	if(count($hStartSystems)==0){
		$hStartSystems=$oSystems->FindSystems("$sSystem");
	}
	$aStartSystemIDs=array_keys($hStartSystems);
	$iMatchSystems=count($aStartSystemIDs);
	if($iMatchSystems<1){
		FormError("Starting system not found.");
		return;
	}
	if($iMatchSystems>1){
		FormWarning("$iMatchSystems systems found; the first matching system will be used.");
	}
	DebugOut("Starting system matches: $iMatchSystems");

	// find systems within specified jump range
	list($iStartSystemID)=$aStartSystemIDs;
	$hRangeSystems=$oSystems->FindSystemsInRange($iStartSystemID,$iJumpRange,$bAvoidLowsec);
	$aRangeSystems=array_keys($hRangeSystems);
	$iFoundSystems=count($aRangeSystems);
	DebugOut("Systems in range: $iFoundSystems");

	// find corps within listed systems
	$aFoundCorps=$oAgents->FindCorpsInSystems($aRangeSystems);
	$iFoundCorps=count($aFoundCorps);
	DebugOut("Corps in range: $iFoundCorps");

	// pre-form the system query list
	$iSearchSystems=0;
	$sSearchSystemQuery="";
	foreach($aRangeSystems as $iRangeSystem){
		if(++$iSearchSystems>1) $sSearchSystemQuery.=" OR";
		$sSearchSystemQuery.=" staStations.solarSystemID=$iRangeSystem";
	}

	// find agents in systems for each corp
	$hFoundCorps=array();
	foreach($aFoundCorps as $iFoundCorp){
		$hCorpLookup=$oAgents->FindCorps($iFoundCorp);
		list($iCorpID)=array_keys($hCorpLookup);
		$sCorpName=$hCorpLookup[$iCorpID];
		$hFoundCorps[$iCorpID]=$sCorpName;
	}

	// display tables sorted by corp
	asort($hFoundCorps);
	foreach($hFoundCorps as $iCorpID=>$sCorpName){
		$sFactionName="";
		foreach($oAgents->GetCorpFaction($iCorpID) as $iID=>$sName){
			$sFactionName.="($sName)";
		}

print <<<CORPHEADER
<b><a class="showInfo" onclick="ARF_showInfo(2,$iCorpID)">$sCorpName</a></b> $sFactionName<br />
CORPHEADER;

		$dbResult=$hDB->query("SELECT staStations.stationName, mapSolarSystems.security, eveNames.itemName, agtAgents.level, ".
			"agtAgents.quality, crpNPCDivisions.divisionName, agtAgents.agentTypeID ".
			"FROM agtAgents,staStations,eveNames,crpNPCDivisions,mapSolarSystems  ".
			"WHERE agtAgents.locationID=staStations.stationID ".
			"AND eveNames.itemID=agtAgents.agentID ".
			"AND staStations.solarSystemID=mapSolarSystems.solarSystemID ".
			"AND crpNPCDivisions.divisionID=agtAgents.divisionID ".
			"AND agtAgents.corporationID=$iCorpID ".
			"AND ($sSearchSystemQuery) ".
			"ORDER BY agtAgents.agentTypeID ASC, agtAgents.level ASC, agtAgents.quality ASC");
		if($hDB->errno>0){
			InternalError("Error $hDB->errno: $hDB->error");
		}else{
			print "<table>";
			while(list($sStation,$iSecurity,$sAgent,$iLevel,$iQuality,$sDivision,$iAgentType)=$dbResult->fetch_row()){
				if($iLevel==1 && $iAgentType==6 && $bShowStoryline==false){continue;}
				if($iLevel==1 && $iAgentType!=6 && $bShowLevel_1==false){continue;}
				if($iLevel==2 && $bShowLevel_2==false){continue;}
				if($iLevel==3 && $bShowLevel_3==false){continue;}
				if($iLevel==4 && $bShowLevel_4==false){continue;}
				if($iLevel==5 && $bShowLevel_5==false){continue;}
			
				print "<pre><tr>";
				$sLevelQuality="L$iLevel"."Q$iQuality";
				$iSecurity=intval($iSecurity*10+0.5)/10.0;
				print "<td>-</td><td>$sLevelQuality</td><td>-</td><td>$iSecurity</td><td>-</td><td>$sStation</td><td>-</td><td>$sAgent</td><td>-</td><td>$sDivision</td>";
//				print "<td>$sStation</td><td>$sAgent</td><td>L$iLevel</td><td>Q$iQuality</td><td>$sDivision</td>";
				print "<td>";
				switch($iAgentType){
					case 2:
					case 4:
						print "";
						break;
					case 3:
						print "(tutorial)";
						break;
					case 6:
						print "(storyline)";
						break;
					case 8:
						print "(event?)";
						break;
					case 9:
						print "(factional warfare)";
						break;
					default:
						print "(unknown? ID=$iAgentType)";
				}
				print "</td></tr></pre>\n";
			}
			print "</table>\n";
		}
		print "<p />";
	}
}

function FormError($sMsg)
{
	print <<<FORM_ERROR
<h4>ERROR: $sMsg</h4>
FORM_ERROR;
}

function FormWarning($sMsg){
	print <<<FORM_WARNING
<h4>WARNING: $sMsg</4><p />
FORM_WARNING;
}

function InternalError($sMsg)
{
	FormError("INTERNAL: $sMsg");
}

print "<hr />";
include_once "../../SupportTag.php";
print "<p />";
include_once "../../Counter.php";
print "<p />";
?>