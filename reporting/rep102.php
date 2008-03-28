<?php

$page_security = 2;
// ----------------------------------------------------------------
// $ Revision:	2.0 $
// Creator:	Joe Hunt
// date_:	2005-05-19
// Title:	Aged Customer Balances
// ----------------------------------------------------------------
$path_to_root="../";

include_once($path_to_root . "includes/session.inc");
include_once($path_to_root . "includes/date_functions.inc");
include_once($path_to_root . "includes/data_checks.inc");
include_once($path_to_root . "gl/includes/gl_db.inc");

//----------------------------------------------------------------------------------------------------

// trial_inquiry_controls();
print_aged_customer_analysis();

function get_invoices($costomer_id, $to)
{
	$todate = date2sql($to);
	$PastDueDays1 = get_company_pref('past_due_days');
	$PastDueDays2 = 2 * $PastDueDays1;

	// Revomed allocated from sql
	$sql = "SELECT ".TB_PREF."sys_types.type_name, ".TB_PREF."debtor_trans.type, ".TB_PREF."debtor_trans.reference,
		".TB_PREF."debtor_trans.tran_date,
		(".TB_PREF."debtor_trans.ov_amount + ".TB_PREF."debtor_trans.ov_gst + ".TB_PREF."debtor_trans.ov_freight + ".TB_PREF."debtor_trans.ov_discount) as Balance,
		IF (".TB_PREF."payment_terms.days_before_due > 0,
			CASE WHEN TO_DAYS('$todate') - TO_DAYS(".TB_PREF."debtor_trans.tran_date) >= ".TB_PREF."payment_terms.days_before_due THEN
				".TB_PREF."debtor_trans.ov_amount + ".TB_PREF."debtor_trans.ov_gst + ".TB_PREF."debtor_trans.ov_freight + ".TB_PREF."debtor_trans.ov_discount
			ELSE
				0
			END,

			CASE WHEN TO_DAYS('$todate') - TO_DAYS(DATE_ADD(DATE_ADD(".TB_PREF."debtor_trans.tran_date,
				INTERVAL 1 MONTH), INTERVAL (".TB_PREF."payment_terms.day_in_following_month -
				DAYOFMONTH(".TB_PREF."debtor_trans.tran_date)) DAY)) >= 0 THEN
					".TB_PREF."debtor_trans.ov_amount + ".TB_PREF."debtor_trans.ov_gst + ".TB_PREF."debtor_trans.ov_freight + ".TB_PREF."debtor_trans.ov_discount
			ELSE
				0
			END
		) AS Due,
		IF (".TB_PREF."payment_terms.days_before_due > 0,
			CASE WHEN TO_DAYS('$todate') - TO_DAYS(".TB_PREF."debtor_trans.tran_date) > ".TB_PREF."payment_terms.days_before_due
				AND TO_DAYS('$todate') - TO_DAYS(".TB_PREF."debtor_trans.tran_date) >= (".TB_PREF."payment_terms.days_before_due + $PastDueDays1) THEN
					".TB_PREF."debtor_trans.ov_amount + ".TB_PREF."debtor_trans.ov_gst + ".TB_PREF."debtor_trans.ov_freight + ".TB_PREF."debtor_trans.ov_discount
			ELSE
				0
			END,

			CASE WHEN TO_DAYS('$todate') - TO_DAYS(DATE_ADD(DATE_ADD(".TB_PREF."debtor_trans.tran_date,
				INTERVAL 1 MONTH), INTERVAL (".TB_PREF."payment_terms.day_in_following_month -
				DAYOFMONTH(".TB_PREF."debtor_trans.tran_date)) DAY)) >= $PastDueDays1 THEN
					".TB_PREF."debtor_trans.ov_amount + ".TB_PREF."debtor_trans.ov_gst + ".TB_PREF."debtor_trans.ov_freight + ".TB_PREF."debtor_trans.ov_discount
			ELSE
				0
			END
		) AS Overdue1,
		IF (".TB_PREF."payment_terms.days_before_due > 0,
			CASE WHEN TO_DAYS('$todate') - TO_DAYS(".TB_PREF."debtor_trans.tran_date) > ".TB_PREF."payment_terms.days_before_due
				AND TO_DAYS('$todate') - TO_DAYS(".TB_PREF."debtor_trans.tran_date) >= (".TB_PREF."payment_terms.days_before_due + $PastDueDays2) THEN
					".TB_PREF."debtor_trans.ov_amount + ".TB_PREF."debtor_trans.ov_gst + ".TB_PREF."debtor_trans.ov_freight + ".TB_PREF."debtor_trans.ov_discount
			ELSE
				0
			END,

			CASE WHEN TO_DAYS('$todate') - TO_DAYS(DATE_ADD(DATE_ADD(".TB_PREF."debtor_trans.tran_date,
				INTERVAL 1 MONTH), INTERVAL (".TB_PREF."payment_terms.day_in_following_month -
				DAYOFMONTH(".TB_PREF."debtor_trans.tran_date)) DAY)) >= $PastDueDays2 THEN
					".TB_PREF."debtor_trans.ov_amount + ".TB_PREF."debtor_trans.ov_gst + ".TB_PREF."debtor_trans.ov_freight + ".TB_PREF."debtor_trans.ov_discount
			ELSE
				0
			END
		) AS Overdue2

		FROM ".TB_PREF."debtors_master,
			".TB_PREF."payment_terms,
			".TB_PREF."debtor_trans,
			".TB_PREF."sys_types

		WHERE ".TB_PREF."sys_types.type_id = ".TB_PREF."debtor_trans.type
		    AND ".TB_PREF."debtor_trans.type <> 13
			AND ".TB_PREF."debtors_master.payment_terms = ".TB_PREF."payment_terms.terms_indicator
			AND ".TB_PREF."debtors_master.debtor_no = ".TB_PREF."debtor_trans.debtor_no
			AND ".TB_PREF."debtor_trans.debtor_no = $costomer_id
			AND ".TB_PREF."debtor_trans.tran_date <= '$todate'
			AND ABS(".TB_PREF."debtor_trans.ov_amount + ".TB_PREF."debtor_trans.ov_gst + ".TB_PREF."debtor_trans.ov_freight + ".TB_PREF."debtor_trans.ov_discount) > 0.004
			ORDER BY ".TB_PREF."debtor_trans.tran_date";


	return db_query($sql, "The customer details could not be retrieved");
}

//----------------------------------------------------------------------------------------------------

function print_aged_customer_analysis()
{
    global $comp_path, $path_to_root;

    include_once($path_to_root . "reporting/includes/pdf_report.inc");

    $to = $_POST['PARAM_0'];
    $fromcust = $_POST['PARAM_1'];
    $currency = $_POST['PARAM_2'];
	$summaryOnly = $_POST['PARAM_3'];
    $graphics = $_POST['PARAM_4'];
    $comments = $_POST['PARAM_5'];
	if ($graphics)
	{
		include_once($path_to_root . "reporting/includes/class.graphic.inc");
		$pg = new graph();
	}

	if ($fromcust == reserved_words::get_all_numeric())
		$from = _('All');
	else
		$from = get_customer_name($fromcust);
    $dec = user_price_dec();

	if ($summaryOnly == 1)
		$summary = _('Summary Only');
	else
		$summary = _('Detailed Report');
	if ($currency == reserved_words::get_all())
	{
		$convert = true;
		$currency = _('Balances in Home Currency');
	}
	else
		$convert = false;

	$PastDueDays1 = get_company_pref('past_due_days');
	$PastDueDays2 = 2 * $PastDueDays1;
	$nowdue = "1-" . $PastDueDays1 . " " . _('Days');
	$pastdue1 = $PastDueDays1 + 1 . "-" . $PastDueDays2 . " " . _('Days');
	$pastdue2 = _('Over') . " " . $PastDueDays2 . " " . _('Days');

	$cols = array(0, 100, 130, 190,	250, 320, 385, 450,	515);
	$headers = array(_('Customer'),	'',	'',	_('Current'), $nowdue, $pastdue1, $pastdue2,
		_('Total Balance'));

	$aligns = array('left',	'left',	'left',	'right', 'right', 'right', 'right',	'right');

    $params =   array( 	0 => $comments,
    					1 => array('text' => _('End Date'), 'from' => $to, 'to' => ''),
    				    2 => array('text' => _('Customer'),	'from' => $from, 'to' => ''),
    				    3 => array('text' => _('Currency'), 'from' => $currency, 'to' => ''),
                    	4 => array('text' => _('Type'),		'from' => $summary,'to' => ''));

	if ($convert)
		$headers[2] = _('Currency');
    $rep = new FrontReport(_('Aged Customer Analysis'), "AgedCustomerAnalysis.pdf", user_pagesize());

    $rep->Font();
    $rep->Info($params, $cols, $headers, $aligns);
    $rep->Header();

	$total = array(0,0,0,0);

	$sql = "SELECT debtor_no, name, curr_code FROM ".TB_PREF."debtors_master ";
	if ($fromcust != reserved_words::get_all_numeric())
		$sql .= "WHERE debtor_no=$fromcust ";
	$sql .= "ORDER BY name";
	$result = db_query($sql, "The customers could not be retrieved");

	while ($myrow=db_fetch($result))
	{
		if (!$convert && $currency != $myrow['curr_code'])
			continue;
		$rep->fontSize += 2;
		$rep->TextCol(0, 3, $myrow['name']);
		if ($convert)
		{
			$rate = get_exchange_rate_from_home_currency($myrow['curr_code'], $to);
			$rep->TextCol(2, 4,	$myrow['curr_code']);
		}
		else
			$rate = 1.0;
		$rep->fontSize -= 2;
		$custrec = get_customer_details($myrow['debtor_no'], $to);
		foreach ($custrec as $i => $value)
			$custrec[$i] *= $rate;
		$total[0] += ($custrec["Balance"] - $custrec["Due"]);
		$total[1] += ($custrec["Due"]-$custrec["Overdue1"]);
		$total[2] += ($custrec["Overdue1"]-$custrec["Overdue2"]);
		$total[3] += $custrec["Overdue2"];
		$total[4] += $custrec["Balance"];
		$str = array(number_format2(($custrec["Balance"] - $custrec["Due"]),$dec),
			number_format2(($custrec["Due"]-$custrec["Overdue1"]),$dec),
			number_format2(($custrec["Overdue1"]-$custrec["Overdue2"]) ,$dec),
			number_format2($custrec["Overdue2"],$dec),
			number_format2($custrec["Balance"],$dec));
		for ($i = 0; $i < count($str); $i++)
			$rep->TextCol($i + 3, $i + 4, $str[$i]);
		$rep->NewLine(1, 2);
		if (!$summaryOnly)
		{
			$res = get_invoices($myrow['debtor_no'], $to);
    		if (db_num_rows($res)==0)
				continue;
    		$rep->Line($rep->row + 4);
			while ($trans=db_fetch($res))
			{
				$rep->NewLine(1, 2);
        		$rep->TextCol(0, 1,	$trans['type_name'], -2);
				$rep->TextCol(1, 2,	$trans['reference'], -2);
				$rep->TextCol(2, 3, sql2date($trans['tran_date']), -2);
				if ($trans['type'] == 11 || $trans['type'] == 12 || $trans['type'] == 2)
				{
					$trans['Balance'] *= -1;
					$trans['Due'] *= -1;
					$trans['Overdue1'] *= -1;
					$trans['Overdue2'] *= -1;
				}
				foreach ($trans as $i => $value)
					$trans[$i] *= $rate;
				$str = array(number_format2(($trans["Balance"] - $trans["Due"]),$dec),
					number_format2(($trans["Due"]-$trans["Overdue1"]),$dec),
					number_format2(($trans["Overdue1"]-$trans["Overdue2"]) ,$dec),
					number_format2($trans["Overdue2"],$dec),
					number_format2($trans["Balance"],$dec));
				for ($i = 0; $i < count($str); $i++)
					$rep->TextCol($i + 3, $i + 4, $str[$i]);
			}
			$rep->Line($rep->row - 8);
			$rep->NewLine(2);
		}
	}
	if ($summaryOnly)
	{
    	$rep->Line($rep->row  + 4);
    	$rep->NewLine();
	}
	$rep->fontSize += 2;
	$rep->TextCol(0, 3, _('Grand Total'));
	$rep->fontSize -= 2;
	for ($i = 0; $i < count($total); $i++)
	{
		$rep->TextCol($i + 3, $i + 4, number_format2($total[$i], $dec));
		if ($graphics && $i < count($total) - 1)
		{
			$pg->y[$i] = abs($total[$i]);
		}
	}
   	$rep->Line($rep->row - 8);
   	if ($graphics)
   	{
   		global $decseps, $graph_skin;
		$pg->x = array(_('Current'), $nowdue, $pastdue1, $pastdue2);
		$pg->title     = $rep->title;
		$pg->axis_x    = _("Days");
		$pg->axis_y    = _("Amount");
		$pg->graphic_1 = $to;
		$pg->type      = $graphics;
		$pg->skin      = $graph_skin;
		$pg->built_in  = false;
		$pg->fontfile  = $path_to_root . "reporting/fonts/Vera.ttf";
		$pg->latin_notation = ($decseps[$_SESSION["wa_current_user"]->prefs->dec_sep()] != ".");
		$filename = $comp_path .'/'. user_company(). "/images/test.png";
		$pg->display($filename, true);
		$w = $pg->width / 1.5;
		$h = $pg->height / 1.5;
		$x = ($rep->pageWidth - $w) / 2;
		$rep->NewLine(2);
		if ($rep->row - $h < $rep->bottomMargin)
			$rep->Header();
		$rep->AddImage($filename, $x, $rep->row - $h, $w, $h);
	}
    $rep->End();
}

?>