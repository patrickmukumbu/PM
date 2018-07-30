<?php
/*** COPYRIGHT NOTICE *********************************************************
 *
 * Copyright 2018 ProjeQtOr - Pascal BERNARD - support@projeqtor.org
 *
 ******************************************************************************
 *** WARNING *** T H I S    F I L E    I S    N O T    O P E N    S O U R C E *
 ******************************************************************************
 *
 * This file is an add-on to ProjeQtOr, packaged as a plug-in module.
 * It is NOT distributed under an open source license. 
 * It is distributed in a proprietary mode, only to the customer who bought
 * corresponding licence. 
 * The company ProjeQtOr remains owner of all add-ons it delivers.
 * Any change to an add-ons without the explicit agreement of the company 
 * ProjeQtOr is prohibited.
 * The diffusion (or any kind if distribution) of an add-on is prohibited.
 * Violators will be prosecuted.
 *    
 *** DO NOT REMOVE THIS NOTICE ************************************************/

include_once '../tool/projeqtor.php';
include_once '../tool/formatter.php';

//Parameters
$idResource=RequestHandler::getId('idResourceAll');
$idMilestone=RequestHandler::getId('idMilestone');
$global=RequestHandler::getBoolean('globale');
$endDate=RequestHandler::getValue('targetDate');

// 

if (!is_array($idResource)) {
	if (trim($idResource)) $idResource=array($idResource);
	else $idResource=array();
}
if(count($idResource)==0){
  echo '<br/><div style="background: #FFDDDD;font-size:150%;color:#808080;text-align:center;padding:20px">';
   echo i18n('messageMandatory',array(i18n('Resource')));
  echo '</div>';
  exit;
}
if( ! $global and ! trim($idMilestone)){
	echo '<br/><div style="background: #FFDDDD;font-size:150%;color:#808080;text-align:center;padding:20px">';
	echo i18n('messageMandatory',array(i18n('Milestone')));
	echo '</div>';
	exit;
}
if( $global and ! trim($endDate) ){
  echo '<br/><div style="background: #FFDDDD;font-size:150%;color:#808080;text-align:center;padding:20px">';
  echo i18n('messageMandatory',array(i18n('colTargetDate')));
  echo '</div>';
  exit;
}

$listRes=array();
$listResAll=array();
$listResObjAll=array();
foreach ($idResource as $id) {
	$res=new ResourceAll($id);
	$listResObjAll[$id]=$res;
	$listRes[$id]=$res->name;
	$listResAll[$id]=$res->name;
	if ($res->isResourceTeam) {
		$aff=new ResourceTeamAffectation();
		$affList=$aff->getSqlElementsFromCriteria(array('idResourceTeam'=>$id));
		foreach ($affList as $aff) {
			$res=new ResourceAll($aff->idResource);
			$listResAll[$aff->idResource]=$res->name;
			$listResObjAll[$aff->idResource]=$res;
		}
	}
}
asort($listRes);
asort($listResAll);

$milestone=new Milestone($idMilestone);
// Header
$headerParameters="";
if (count($idResource)>0) {
  $headerParameters.= i18n("colIdResource") . ' : ' . implode(', ', $listRes). '<br/>';
}
if ( trim($idMilestone)) {
  $headerParameters.= i18n("colIdMilestone") . ' : ' . htmlEncode(SqlList::getNameFromId('Milestone',$idMilestone)) . '<br/>';
} 

if ( trim($endDate)) {
  $headerParameters.= i18n("colTargetDate") . ' : ' . htmlFormatDate($endDate) . '<br/>';
}

include "header.php";

$arrayPossible=array();
$arrayPlanned=array();
$arrayMargin=array();
$sumPossible=0;
$sumPlanned=0;
$sumMargin=0;

// Get Data
$today=date('Y-m-d');
if (!$global) $endDate=$milestone->MilestonePlanningElement->validatedEndDate;
if(!$endDate){
  echo '<br/><div style="background: #FFDDDD;font-size:150%;color:#808080;text-align:center;padding:20px">';
  echo i18n('scleMilestoneWithoutValidatedDate');
  echo '</div>';
  exit;
}
if($endDate<$today){
  echo '<br/><div style="background: #FFDDDD;font-size:150%;color:#808080;text-align:center;padding:20px">';
  echo i18n('scleMilestoneEndDateError').' ('.htmlFormatDate($endDate).')';
  echo '</div>';
  exit;
}

$work=new Work();
$pe=new PlanningElement();
$peTable=$pe->getDatabaseTableName();
$act=new Activity();
$actTable=$act->getDatabaseTableName();
$ass=new Assignment();
foreach($listResObjAll as $res) {
	$possible=0;
	$planned=0;
	$margin=0;
	if ($res->isResourceTeam) $periods=ResourceTeamAffectation::buildResourcePeriods($res->id);
	// Possible from Calendar
	for ($currentDate=$today;$currentDate<=$endDate;$currentDate=addDaysToDate($currentDate, 1)) {
		if (isOpenDay($currentDate,$res->idCalendarDefinition)) {
			if ($res->isResourceTeam) {
				$period=ResourceTeamAffectation::findPeriod($currentDate, $periods);
				$capacity=$periods[$period]['rate'];
			} else {
				$capacity=$res->capacity;
			}
			$possible+=$capacity;
	  }
	}
	// Subtract real from possible
	$where="idResource=$res->id and workDate>='$today' and workDate<='$endDate'";
	$sumReal=$work->sumSqlElementsFromCriteria('work', null, $where);
	$possible-= $sumReal;
	if ($global) {
	  // Get left work on Activities expected at target end date
	  $where="idResource=$res->id and refType='Activity' and refId in (select refId from $peTable where refType='Activity' and leftWork>0 and validatedEndDate<='$endDate')";
	   
	} else {
	  // Get left work on Activities linked to Milstone
	  $where="idResource=$res->id and refType='Activity' and refId in(select id from $actTable where idMilestone=$milestone->id)";
  } 
	$sumLeft=$ass->sumSqlElementsFromCriteria('leftWork', null, $where);
  $planned=$sumLeft;
	// Sums
	$margin=$possible-$planned;
	$arrayPossible[$res->id]=$possible;
	$arrayPlanned[$res->id]=$planned;
	$arrayMargin[$res->id]=$margin;
	if (!$res->isResourceTeam) $sumPossible+=$possible;
	$sumPlanned+=$planned;
}
$sumMargin=$sumPossible-$sumPlanned;

// Display Data

echo "<table style='margin:auto;margin-top:20px;text-align:center'>";
echo "  <tr><td colspan='4' style='font-size:100%;'><b>";
if ($global) echo i18n("colScleGlobalMarginHelp",array(htmlFormatDate($endDate)));
else echo i18n("colScleMilestoneMarginHelp",array($milestone->name));
echo "</b><br/><i>".i18n('from').' '.htmlFormatDate($today).' '.i18n('to').' '.htmlFormatDate($endDate)."</i><br/><br/></td></tr>";
echo "  <tr>";
echo "    <td class='reportTableHeader' style='width:240px'>".i18n('Resource')."</td>";
echo "    <td class='reportTableHeader' style='width:140px'>".i18n('sclePossibleWork')."</td>";
echo "    <td class='reportTableHeader' style='width:140px'>".i18n('sclePlannedWork')."</td>";
echo "    <td class='reportTableHeader' style='width:140px'>".i18n('scleMargin')."</td>";
echo "  </tr>";
foreach ($listResAll as $idRes=>$nameRes) {
	$resObj=$listResObjAll[$idRes];
	$icon=($resObj->isResourceTeam)?'<div style="float:right" class="iconTeam16 iconTeam iconSize16" ></div>':'';
	$possible=$arrayPossible[$idRes];
	$planned=$arrayPlanned[$idRes];
	$margin=$arrayMargin[$idRes];
	$possibleDisplay= Work::displayImputationWithUnit($possible);
	$plannedDisplay= Work::displayImputationWithUnit($planned);
	$marginDisplay=Work::displayImputationWithUnit($margin);
	if ($resObj->isResourceTeam) {
		$possibleDisplay='<span style="color:#D0D0D0">'.$possibleDisplay.'</span>';
		$marginDisplay='<span style="color:#D0D0D0">'.$marginDisplay.'</span>';
	} else if ($margin<0) {
		$marginDisplay='<span style="color:#A00000">'.$marginDisplay.'</span>';
	} else if ($margin>0) {
		$marginDisplay='<span style="color:#00A000">'.$marginDisplay.'</span>';
	}
	
	echo "  <tr>";
	echo "    <td class='reportTableLineHeader' style='width:240px;text-align:left;padding:2px 5px;'>".$nameRes.$icon."</td>";
	echo "    <td class='reportTableData' style='width:140px;padding:2px 5px;'>".$possibleDisplay."</td>";
	echo "    <td class='reportTableData' style='width:140px;padding:2px 5px;'>".$plannedDisplay."</td>";
	echo "    <td class='reportTableData' style='width:140px;padding:2px 5px;'>".$marginDisplay."</td>";
	echo "  </tr>";
}
$possibleDisplay=Work::displayImputationWithUnit($sumPossible);
$plannedDisplay=Work::displayImputationWithUnit($sumPlanned);
$marginDisplay=Work::displayImputationWithUnit($sumMargin);
if ($sumMargin<0) {
	$marginDisplay='<span style="color:#A00000">'.$marginDisplay.'</span>';
} else if ($sumMargin>0) {
	$marginDisplay='<span style="color:#00A000">'.$marginDisplay.'</span>';
}
echo "  <tr>";
echo "    <td class='reportTableHeader' style='width:240px;padding:2px 5px;'>".i18n('sum')."</td>";
echo "    <td class='reportTableLineHeader' style='font-weight:bold;width:140px;text-align:center;padding:2px 5px;'>".$possibleDisplay."</td>";
echo "    <td class='reportTableLineHeader' style='font-weight:bold;width:140px;text-align:center;padding:2px 5px;'>".$plannedDisplay."</td>";
echo "    <td class='reportTableLineHeader' style='font-weight:bold;width:140px;text-align:center;padding:2px 5px;'>".$marginDisplay."</td>";
echo "  </tr>";
echo "</table>";