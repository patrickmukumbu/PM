<?php
/*** COPYRIGHT NOTICE *********************************************************
 *
 * Copyright 2009-2017 ProjeQtOr - Pascal BERNARD - support@projeqtor.org
 * Contributors : -
 *
 * This file is part of ProjeQtOr.
 * 
 * ProjeQtOr is free software: you can redistribute it and/or modify it under 
 * the terms of the GNU Affero General Public License as published by the Free 
 * Software Foundation, either version 3 of the License, or (at your option) 
 * any later version.
 * 
 * ProjeQtOr is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS 
 * FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for 
 * more details.
 *
 * You should have received a copy of the GNU Affero General Public License along with
 * ProjeQtOr. If not, see <http://www.gnu.org/licenses/>.
 *
 * You can get complete code of ProjeQtOr, other resource, help and information
 * about contributors at http://www.projeqtor.org 
 *     
 *** DO NOT REMOVE THIS NOTICE ************************************************/

//
// THIS IS THE PRODUCT STRUCTURE REPORT
//
include_once '../tool/projeqtor.php';
include_once '../tool/formatter.php';

$objectClass="";
if (array_key_exists('objectClass', $_REQUEST)){
  $objectClass=trim($_REQUEST['objectClass']);
}
Security::checkValidClass($objectClass);
$objectId="";
if (array_key_exists('objectId', $_REQUEST)){
  $objectId=trim($_REQUEST['objectId']);
}
Security::checkValidId($objectId);
if (!$objectClass or !$objectId) return;
if ($objectClass!='ProductVersion' and $objectClass!='ComponentVersion') return;

$format="print";
if (array_key_exists('format', $_REQUEST)){
  $format=trim($_REQUEST['format']);
}

$item=new $objectClass($objectId);

$canRead=securityGetAccessRightYesNo('menu' . $objectClass, 'read', $item)=="YES";
if (!$canRead) exit;

if ($objectClass=='VersionProduct') {
  //$parentVersionProducts=$item->getParentProducts();
}
$result=array();
$result=getSubItems($item,$result);

if ($format=='print') {
  echo "<table style='width:100%'>";
  echo "<tr><td style='width:50%;vertical-align:top;padding:5px;'>";
  // Items
  echo "<table style='width:100%;'>";
  echo "<tr><th style='padding:5px;text-align:center;'>".i18n('sectionComposition',array(i18n($objectClass),intval($objectId).' - '.$item->name)).'</th></tr>';
  foreach ($result as $item) {
    echo "<tr><td>";
    //CHANGE by qCazelles - dateComposition
    //Old
    //showProduct($item['class'], $item['id'], $item['name']);
    //NEW
    showProduct($item['class'], $item['id'], $item['name'], $item['deliveryDate']);
    //END CHANGE qCazelles - dateComposition
    echo "</td></tr>";
  }
  echo "</table>";
  echo "</td><td style='width:50%;vertical-align:top;padding:5px;'>";
  // Parents  
  //echo "<table style='width:100%;'>";
  //echo "<tr><th style='padding:5px;text-align:center;'>".i18n('parentProductList').'</th></tr>';
  /*foreach ($parentProducts as $prdId=>$prdName) {
    echo "<tr><td>";
    showProduct('Product', $prdId, $prdName);
    echo "</td></tr>";
  }*/
  //echo "</table>";
  echo "</td></tr>";
  echo "</table>";
  //CHANGE qCazelles - DeliveryDateXLS - Ticket #126
} else if ($format=='csv' and Parameter::getGlobalParameter("displayMilestonesStartDelivery") != 'YES') {
  echo "Class;Id;Name\n";
  foreach ($result as $item) {
    echo $item['class'].';'.$item['id'].';'.$item['name']."\n";
  }
} else if ($format=='csv') {
  echo "Class;Id;Name;Delivery date\n";
  foreach ($result as $item) {
    echo $item['class'].';'.$item['id'].';'.$item['name'].';'.$item['deliveryDate']."\n";
  }
//END CHANGE qCazelles - DeliveryDateXLS - Ticket #126
} else {
  errorLog("productStructure : incorrect format '$format'");
  exit;
}

function getSubItems($item,$result){
  /*if (get_class($item)=='ProductVersion') {
    $crit=array('idProductVersion'=>$item->id);
    $lst=$item->getSqlElementsFromCriteria($crit);
    foreach ($lst as $prd) {
      $result[$prd->id]=array('class'=>'Product','id'=>$prd->id,'name'=>$prd->name);
      $result=getSubItems($prd,$result);
    }
  }*/
  $ps=new ProductVersionStructure();
  $psList=$ps->getSqlElementsFromCriteria(array('idProductVersion'=>$item->id));
  foreach ($psList as $ps) {
    $comp=new ComponentVersion($ps->idComponentVersion);
    //CHANGE by qCazelles - dateComposition
    //Old
    //$result[$ps->idComponentVersion]=array('class'=>get_class($comp),'id'=>$comp->id,'name'=>$comp->name);
    //New
    $deliveryDate=null;
    if ($comp->realDeliveryDate) $deliveryDate=$comp->realDeliveryDate;
    else if ($comp->plannedDeliveryDate) $deliveryDate=$comp->plannedDeliveryDate;
    else if ($comp->initialDeliveryDate) $deliveryDate=$comp->initialDeliveryDate;
    $result[$ps->idComponentVersion]=array('class'=>get_class($comp),'id'=>$comp->id,'name'=>$comp->name,'deliveryDate'=>$deliveryDate);
    //END CHANGE qCazelles - dateComposition
    $result=getSubItems($comp,$result);
  }
  return $result;
}

//CHANGE by qCazelles - dateComposition
//Old
//function showProduct($class,$id,$name) {
//New
function showProduct($class,$id,$name,$deliveryDate=null) {
  $name="#$id - $name";
  $style="width:100%";
  $item=new $class($id);
  //Old
  /*
  echo '<table style="'.$style.'"><tr><td style="padding-left:5px;padding-top:2px;width:20px;" class="icon'.$class.'16" >&nbsp;</td>'
      .'<td style="padding:0px 5px;vertical-align:middle;">'.$name.'</td></tr></table>';
  */
  //New
  echo '<table style="'.$style.'"><tr><td style="padding-left:5px;padding-top:2px;width:20px;" class="icon'.$class.'16" >&nbsp;</td>'
      .'<td style="padding:0px 5px;vertical-align:middle;">'.$name.'</td>';
      if ($deliveryDate) echo '<td style="padding:0px 5px;vertical-align:middle;">'.htmlFormatDate($deliveryDate).'</td>';
  echo '</tr></table>';
}
//END CHANGE qCazelles - dateComposition