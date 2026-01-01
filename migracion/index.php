<?php
// Migration tool for PuertaSevilla SQL -> Dolibarr (Third parties only)
// - Upload SQL dump
// - Parse INSERT INTO `tercero`
// - Upsert third parties
// - Store IBANs into Dolibarr third-party bank accounts (RIB)

require '../main.inc.php';

require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/ctypent.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/companybankaccount.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
require_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php'; // ContratLigne is inside

$langs->loadLangs(array('companies', 'admin'));

if (empty($user->admin)) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

/**
 * @param string|null $value
 * @return string|null
 */
function psv_nullif_empty($value)
{
	if ($value === null) return null;
	$value = trim((string) $value);
	if ($value === '' || strtoupper($value) === 'NULL') return null;
	return $value;
}

/**
 * Extracts an IBAN-like substring, normalized (uppercase, no spaces).
 * Returns null if nothing plausible.
 *
 * @param string|null $raw
 * @return string|null
 */
function psv_normalize_iban($raw)
{
	$raw = psv_nullif_empty($raw);
	if ($raw === null) return null;

	$upper = strtoupper($raw);
	// Remove common prefixes like "BANKIA-" keeping the IBAN part
	if (preg_match('/([A-Z]{2}[0-9]{2}[A-Z0-9]{10,32})/', preg_replace('/\s+/', '', $upper), $m)) {
		return $m[1];
	}

	// Fallback: keep alphanum only and retry
	$alnum = preg_replace('/[^A-Z0-9]/', '', $upper);
	if (preg_match('/([A-Z]{2}[0-9]{2}[A-Z0-9]{10,32})/', $alnum, $m2)) {
		return $m2[1];
	}

	return null;
}

/**
 * Parse a date string (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS) into a timestamp.
 * Returns 0 when empty/invalid.
 *
 * @param string|null $raw
 * @return int
 */
function psv_parse_date_ts($raw)
{
	$raw = psv_nullif_empty($raw);
	if ($raw === null) return 0;
	$raw = trim($raw);
	if ($raw === '' || $raw === '0000-00-00' || strpos($raw, '0000-00-00') === 0) return 0;
	$ts = dol_stringtotime($raw);
	if (!$ts || $ts < 0) return 0;
	return (int) $ts;
}

/**
 * Ensure needed extrafields exist for third parties.
 *
 * @param DoliDB $db
 * @return array{ok:bool,errors:string[]}
 */
function psv_ensure_societe_extrafields($db)
{
	$errors = array();
	$extrafields = new ExtraFields($db);
	$extrafields->fetch_name_optionals_label('societe');

	$labels = array();
	if (!empty($extrafields->attributes['societe']['label']) && is_array($extrafields->attributes['societe']['label'])) {
		$labels = $extrafields->attributes['societe']['label'];
	}

	$toCreate = array(
		array(
			'attrname' => 'psv_id_origen_tercero',
			'label' => 'ID origen (PuertaSevilla)',
			'type' => 'int',
			'pos' => 1000,
			'size' => '11',
			'elementtype' => 'thirdparty',
			'unique' => 1,
			'required' => 0,
			'help' => 'Identificador del tercero en la BBDD original de PuertaSevilla. Se usa para actualizaciones (upsert) sin duplicados.',
			'param' => '',
		),
		array(
			'attrname' => 'psv_nacionalidad',
			'label' => 'Nacionalidad (PuertaSevilla)',
			'type' => 'varchar',
			'pos' => 1002,
			'size' => '64',
			'elementtype' => 'thirdparty',
			'unique' => 0,
			'required' => 0,
			'help' => 'Nacionalidad importada desde la BBDD original (si existe).',
			'param' => '',
		),
		array(
			'attrname' => 'psv_forma_pago_origen',
			'label' => 'Forma de pago (origen)',
			'type' => 'varchar',
			'pos' => 1003,
			'size' => '64',
			'elementtype' => 'thirdparty',
			'unique' => 0,
			'required' => 0,
			'help' => 'Texto de forma de pago tal como venía en el sistema original (efectivo, transferencia, etc.).',
			'param' => '',
		),
	);

	foreach ($toCreate as $def) {
		if (array_key_exists($def['attrname'], $labels)) {
			continue;
		}

		$res = $extrafields->addExtraField(
			$def['attrname'],
			$def['label'],
			$def['type'],
			$def['pos'],
			$def['size'],
			$def['elementtype'],
			$def['unique'],
			$def['required'],
			'',
			$def['param'],
			1,
			'',
			'-1',
			(!empty($def['help']) ? $def['help'] : '')
		);
		if ($res <= 0) {
			$errors[] = 'No se pudo crear el extrafield '.$def['attrname'].': '.$extrafields->error;
		}
	}

	return array('ok' => empty($errors), 'errors' => $errors);
}

/**
 * Ensure needed extrafields exist for projects (viviendas).
 *
 * @param DoliDB $db
 * @return array{ok:bool,errors:string[]}
 */
function psv_ensure_projet_extrafields($db)
{
	$errors = array();
	$extrafields = new ExtraFields($db);
	$extrafields->fetch_name_optionals_label('projet');

	$labels = array();
	if (!empty($extrafields->attributes['projet']['label']) && is_array($extrafields->attributes['projet']['label'])) {
		$labels = $extrafields->attributes['projet']['label'];
	}

	$toCreate = array(
		array(
			'attrname' => 'psv_id_origen_vivienda',
			'label' => 'ID origen (vivienda)',
			'type' => 'int',
			'pos' => 2000,
			'size' => '11',
			'elementtype' => 'projet',
			'unique' => 1,
			'required' => 0,
			'help' => 'Identificador de la vivienda en la BBDD original. Se usa para actualizaciones (upsert) sin duplicados.',
			'param' => '',
		),
		array(
			'attrname' => 'psv_ref_vivienda',
			'label' => 'Referencia vivienda (origen)',
			'type' => 'int',
			'pos' => 2001,
			'size' => '11',
			'elementtype' => 'projet',
			'unique' => 0,
			'required' => 0,
			'help' => 'Campo ref del sistema original (número interno de vivienda).',
			'param' => '',
		),
		array(
			'attrname' => 'psv_direccion',
			'label' => 'Dirección (origen)',
			'type' => 'varchar',
			'pos' => 2002,
			'size' => '255',
			'elementtype' => 'projet',
			'unique' => 0,
			'required' => 0,
			'help' => 'Dirección importada desde la vivienda original.',
			'param' => '',
		),
		array(
			'attrname' => 'psv_localidad',
			'label' => 'Localidad (origen)',
			'type' => 'varchar',
			'pos' => 2003,
			'size' => '255',
			'elementtype' => 'projet',
			'unique' => 0,
			'required' => 0,
			'help' => 'Localidad importada desde la vivienda original.',
			'param' => '',
		),
		array(
			'attrname' => 'psv_propietario_id_origen',
			'label' => 'ID propietario (origen)',
			'type' => 'int',
			'pos' => 2004,
			'size' => '11',
			'elementtype' => 'projet',
			'unique' => 0,
			'required' => 0,
			'help' => 'ID del propietario en el sistema original (tabla tercero).',
			'param' => '',
		),
		array(
			'attrname' => 'psv_superficie',
			'label' => 'Superficie (m²) (origen)',
			'type' => 'varchar',
			'pos' => 2005,
			'size' => '64',
			'elementtype' => 'projet',
			'unique' => 0,
			'required' => 0,
			'help' => 'Texto de superficie tal como venía en el sistema original.',
			'param' => '',
		),
		array(
			'attrname' => 'psv_bagno',
			'label' => 'Baños (origen)',
			'type' => 'varchar',
			'pos' => 2006,
			'size' => '64',
			'elementtype' => 'projet',
			'unique' => 0,
			'required' => 0,
			'help' => 'Número de baños (origen).',
			'param' => '',
		),
		array(
			'attrname' => 'psv_dormitorio',
			'label' => 'Dormitorios (origen)',
			'type' => 'varchar',
			'pos' => 2007,
			'size' => '64',
			'elementtype' => 'projet',
			'unique' => 0,
			'required' => 0,
			'help' => 'Número de dormitorios (origen).',
			'param' => '',
		),
		array(
			'attrname' => 'psv_suministro',
			'label' => 'Suministro (origen)',
			'type' => 'varchar',
			'pos' => 2008,
			'size' => '255',
			'elementtype' => 'projet',
			'unique' => 0,
			'required' => 0,
			'help' => 'Valor de suministro (p.ej. "inquilino") del sistema original.',
			'param' => '',
		),
		array(
			'attrname' => 'psv_catastro',
			'label' => 'Referencia catastral (origen)',
			'type' => 'varchar',
			'pos' => 2009,
			'size' => '255',
			'elementtype' => 'projet',
			'unique' => 0,
			'required' => 0,
			'help' => 'Referencia catastral importada (si existe).',
			'param' => '',
		),
		array(
			'attrname' => 'psv_certificado',
			'label' => 'Certificado (origen)',
			'type' => 'varchar',
			'pos' => 2010,
			'size' => '255',
			'elementtype' => 'projet',
			'unique' => 0,
			'required' => 0,
			'help' => 'Campo certificado de la vivienda original (si existe).',
			'param' => '',
		),
		array(
			'attrname' => 'psv_color',
			'label' => 'Color (origen)',
			'type' => 'varchar',
			'pos' => 2011,
			'size' => '50',
			'elementtype' => 'projet',
			'unique' => 0,
			'required' => 0,
			'help' => 'Color importado (origen).',
			'param' => '',
		),
		array(
			'attrname' => 'psv_estado',
			'label' => 'Estado (origen)',
			'type' => 'int',
			'pos' => 2012,
			'size' => '4',
			'elementtype' => 'projet',
			'unique' => 0,
			'required' => 0,
			'help' => 'Estado numérico de la vivienda en el sistema original.',
			'param' => '',
		),
		array(
			'attrname' => 'psv_compania',
			'label' => 'Compañía (origen)',
			'type' => 'varchar',
			'pos' => 2013,
			'size' => '50',
			'elementtype' => 'projet',
			'unique' => 0,
			'required' => 0,
			'help' => 'Campo compañía (origen) si aplica.',
			'param' => '',
		),
		array(
			'attrname' => 'psv_ncontrato',
			'label' => 'Nº contrato (origen)',
			'type' => 'varchar',
			'pos' => 2014,
			'size' => '50',
			'elementtype' => 'projet',
			'unique' => 0,
			'required' => 0,
			'help' => 'Número de contrato asociado (origen) si existe.',
			'param' => '',
		),
		array(
			'attrname' => 'psv_nombre_compania',
			'label' => 'Nombre compañía (origen)',
			'type' => 'varchar',
			'pos' => 2015,
			'size' => '255',
			'elementtype' => 'projet',
			'unique' => 0,
			'required' => 0,
			'help' => 'Nombre de compañía asociado (origen) si existe.',
			'param' => '',
		),
	);

	foreach ($toCreate as $def) {
		if (array_key_exists($def['attrname'], $labels)) {
			continue;
		}

		$res = $extrafields->addExtraField(
			$def['attrname'],
			$def['label'],
			$def['type'],
			$def['pos'],
			$def['size'],
			$def['elementtype'],
			$def['unique'],
			$def['required'],
			'',
			$def['param'],
			1,
			'',
			'-1',
			(!empty($def['help']) ? $def['help'] : '')
		);
		if ($res <= 0) {
			$errors[] = 'No se pudo crear el extrafield '.$def['attrname'].' (proyecto): '.$extrafields->error;
		}
	}

	return array('ok' => empty($errors), 'errors' => $errors);
}

/**
 * Ensure a contact type exists to link tenants as contacts on projects.
 * We create a c_type_contact for element 'project' with code 'INQUILINO'.
 *
 * @param DoliDB $db
 * @return array{ok:bool,errors:string[]}
 */
function psv_ensure_project_contact_type_inquilino($db)
{
	$errors = array();
	$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."c_type_contact";
	$sql .= " WHERE element='project' AND source='external' AND code='INQUILINO'";
	$sql .= " LIMIT 1";
	$resql = $db->query($sql);
	if (!$resql) {
		$errors[] = 'Error leyendo c_type_contact: '.$db->lasterror();
		return array('ok' => false, 'errors' => $errors);
	}
	$obj = $db->fetch_object($resql);
	if ($obj && !empty($obj->rowid)) {
		return array('ok' => true, 'errors' => array());
	}

	$ins = "INSERT INTO ".MAIN_DB_PREFIX."c_type_contact (element, source, code, libelle, active, module, position) VALUES (";
	$ins .= "'project', 'external', 'INQUILINO', 'Inquilino', 1, 'psv', 90";
	$ins .= ")";
	$resins = $db->query($ins);
	if (!$resins) {
		// If it already exists due to race/previous run, accept.
		$err = $db->lasterror();
		if (stripos($err, 'Duplicate') === false) {
			$errors[] = 'No se pudo crear tipo de contacto INQUILINO en proyectos: '.$err;
			return array('ok' => false, 'errors' => $errors);
		}
	}

	return array('ok' => true, 'errors' => array());
}

/**
 * Ensure needed extrafields exist for contracts (contratoUsuario).
 *
 * @param DoliDB $db
 * @return array{ok:bool,errors:string[]}
 */
function psv_ensure_contrat_extrafields($db)
{
	$errors = array();
	$extrafields = new ExtraFields($db);
	$extrafields->fetch_name_optionals_label('contrat');

	$labels = array();
	if (!empty($extrafields->attributes['contrat']['label']) && is_array($extrafields->attributes['contrat']['label'])) {
		$labels = $extrafields->attributes['contrat']['label'];
	}

	$toCreate = array(
		array(
			'attrname' => 'psv_id_origen_contrato_usuario',
			'label' => 'ID origen (contrato usuario)',
			'type' => 'int',
			'pos' => 3000,
			'size' => '11',
			'elementtype' => 'contrat',
			'unique' => 1,
			'required' => 0,
			'help' => 'Identificador del contratoUsuario en la BBDD original. Se usa para upsert.',
			'param' => '',
		),
		array(
			'attrname' => 'psv_vivienda_id_origen',
			'label' => 'ID vivienda (origen)',
			'type' => 'int',
			'pos' => 3001,
			'size' => '11',
			'elementtype' => 'contrat',
			'unique' => 0,
			'required' => 0,
			'help' => 'ID de vivienda asociado en el sistema original.',
			'param' => '',
		),
		array(
			'attrname' => 'psv_propietario_id_origen',
			'label' => 'ID propietario (origen)',
			'type' => 'int',
			'pos' => 3002,
			'size' => '11',
			'elementtype' => 'contrat',
			'unique' => 0,
			'required' => 0,
			'help' => 'ID de propietario asociado en el sistema original.',
			'param' => '',
		),
		array(
			'attrname' => 'psv_inquilino_id_origen',
			'label' => 'ID inquilino (origen)',
			'type' => 'int',
			'pos' => 3003,
			'size' => '11',
			'elementtype' => 'contrat',
			'unique' => 0,
			'required' => 0,
			'help' => 'ID del inquilino principal en el sistema original.',
			'param' => '',
		),
		array(
			'attrname' => 'psv_inquilino2_id_origen',
			'label' => 'ID inquilino 2 (origen)',
			'type' => 'int',
			'pos' => 3004,
			'size' => '11',
			'elementtype' => 'contrat',
			'unique' => 0,
			'required' => 0,
			'help' => 'ID del segundo inquilino (si existe) en el sistema original.',
			'param' => '',
		),
		array(
			'attrname' => 'psv_finicio_origen',
			'label' => 'Fecha inicio (origen)',
			'type' => 'date',
			'pos' => 3005,
			'size' => '32',
			'elementtype' => 'contrat',
			'unique' => 0,
			'required' => 0,
			'help' => 'Fecha de inicio tal como venía en el sistema original.',
			'param' => '',
		),
		array(
			'attrname' => 'psv_ffin_origen',
			'label' => 'Fecha fin (origen)',
			'type' => 'date',
			'pos' => 3006,
			'size' => '32',
			'elementtype' => 'contrat',
			'unique' => 0,
			'required' => 0,
			'help' => 'Fecha de fin tal como venía en el sistema original.',
			'param' => '',
		),
		array(
			'attrname' => 'psv_periodo_origen',
			'label' => 'Periodo (origen)',
			'type' => 'varchar',
			'pos' => 3007,
			'size' => '32',
			'elementtype' => 'contrat',
			'unique' => 0,
			'required' => 0,
			'help' => 'Periodo (p.ej. mensual) tal como venía en el origen.',
			'param' => '',
		),
		array(
			'attrname' => 'psv_importe_origen',
			'label' => 'Importe (origen)',
			'type' => 'varchar',
			'pos' => 3008,
			'size' => '32',
			'elementtype' => 'contrat',
			'unique' => 0,
			'required' => 0,
			'help' => 'Importe del contrato (origen), sin normalizar.',
			'param' => '',
		),
		array(
			'attrname' => 'psv_iva_origen',
			'label' => 'IVA (origen)',
			'type' => 'varchar',
			'pos' => 3009,
			'size' => '16',
			'elementtype' => 'contrat',
			'unique' => 0,
			'required' => 0,
			'help' => 'IVA del contrato (origen) si aplica.',
			'param' => '',
		),
		array(
			'attrname' => 'psv_dia_pago',
			'label' => 'Día de pago (origen)',
			'type' => 'int',
			'pos' => 3010,
			'size' => '4',
			'elementtype' => 'contrat',
			'unique' => 0,
			'required' => 0,
			'help' => 'Día de pago del contrato según el origen.',
			'param' => '',
		),
		array(
			'attrname' => 'psv_vigente',
			'label' => 'Vigente (origen)',
			'type' => 'int',
			'pos' => 3011,
			'size' => '4',
			'elementtype' => 'contrat',
			'unique' => 0,
			'required' => 0,
			'help' => 'Marca de vigente del contrato en el sistema original.',
			'param' => '',
		),
		array(
			'attrname' => 'psv_ccc_origen',
			'label' => 'CCC/IBAN (origen)',
			'type' => 'varchar',
			'pos' => 3012,
			'size' => '128',
			'elementtype' => 'contrat',
			'unique' => 0,
			'required' => 0,
			'help' => 'Texto de CCC/IBAN del contrato en el origen.',
			'param' => '',
		),
		array(
			'attrname' => 'psv_entidad_ccc_origen',
			'label' => 'Entidad CCC (origen)',
			'type' => 'varchar',
			'pos' => 3013,
			'size' => '128',
			'elementtype' => 'contrat',
			'unique' => 0,
			'required' => 0,
			'help' => 'Entidad/descripcion de CCC (origen) si existe.',
			'param' => '',
		),
		array(
			'attrname' => 'psv_fk_rib',
			'label' => 'Cuenta bancaria (RIB) propietario',
			'type' => 'sellist',
			'pos' => 3014,
			'size' => '',
			'elementtype' => 'contrat',
			'unique' => 0,
			'required' => 0,
			'help' => 'Cuenta bancaria del propietario asociada al contrato.',
			'param' => 'societe_rib:label:rowid::fk_soc=__SOCID__',
		),
	);

	foreach ($toCreate as $def) {
		if (array_key_exists($def['attrname'], $labels)) {
			continue;
		}

		$res = $extrafields->addExtraField(
			$def['attrname'],
			$def['label'],
			$def['type'],
			$def['pos'],
			$def['size'],
			$def['elementtype'],
			$def['unique'],
			$def['required'],
			'',
			$def['param'],
			1,
			'',
			'-1',
			(!empty($def['help']) ? $def['help'] : '')
		);
		if ($res <= 0) {
			$errors[] = 'No se pudo crear el extrafield '.$def['attrname'].' (contrato): '.$extrafields->error;
		}
	}

	return array('ok' => empty($errors), 'errors' => $errors);
}

/**
 * Ensure c_typent dictionary entries exist for PSV roles.
 *
 * @param DoliDB $db
 * @param User $user
 * @return array{ok:bool,ids:array<string,int>,created:array<string,bool>,errors:string[]}
 */
function psv_ensure_typent_roles($db, $user)
{
	$errors = array();
	$ids = array();
	$created = array();

	// c_typent.id is a PRIMARY KEY without auto-increment, we must provide an id.
	$maxid = 0;
	$resqlMax = $db->query("SELECT MAX(id) as maxid FROM ".MAIN_DB_PREFIX."c_typent");
	if ($resqlMax) {
		$objMax = $db->fetch_object($resqlMax);
		$maxid = ($objMax && $objMax->maxid !== null) ? (int) $objMax->maxid : 0;
	}
	$nextId = ($maxid < 9000 ? 9000 : ($maxid + 1));

	// WARNING: c_typent.code is varchar(12). Keep codes <= 12 chars.
	$defs = array(
		'PSV_ADMIN' => 'Administrador',
		'PSV_PROP' => 'Propietario',
		'PSV_INQ' => 'Inquilino',
	);

	foreach ($defs as $code => $label) {
		$sql = "SELECT id FROM ".MAIN_DB_PREFIX."c_typent WHERE code = '".$db->escape($code)."' OR TRIM(code) = '".$db->escape($code)."' LIMIT 1";
		$resql = $db->query($sql);
		if (!$resql) {
			$errors[] = 'Error leyendo diccionario c_typent: '.$db->lasterror();
			continue;
		}
		$obj = $db->fetch_object($resql);
		if ($obj && !empty($obj->id)) {
			$ids[$code] = (int) $obj->id;
			$created[$code] = false;
			continue;
		}

		$ct = new Ctypent($db);
		// Assign an explicit id (required by schema)
		$tries = 0;
		$createdOk = false;
		while ($tries < 50 && !$createdOk) {
			$ct->id = $nextId;
			$ct->code = $code;
			$ct->libelle = $label;
			$ct->active = 1;
			$ct->module = 'psv';
			$newid = $ct->create($user);
			if ($newid > 0) {
				$ids[$code] = (int) $newid;
				$created[$code] = true;
				$createdOk = true;
				$nextId = $newid + 1;
				break;
			}

			// If code already exists (unique uk_c_typent), fetch its id and accept.
			if (!empty($ct->error) && (strpos($ct->error, 'uk_c_typent') !== false || strpos($ct->error, 'Duplicate entry') !== false)) {
				$sql2 = "SELECT id FROM ".MAIN_DB_PREFIX."c_typent WHERE code = '".$db->escape($code)."' OR TRIM(code) = '".$db->escape($code)."' LIMIT 1";
				$resql2 = $db->query($sql2);
				if ($resql2) {
					$obj2 = $db->fetch_object($resql2);
					if ($obj2 && !empty($obj2->id)) {
						$ids[$code] = (int) $obj2->id;
						$created[$code] = false;
						$createdOk = true;
						break;
					}
				}
			}

			// Otherwise assume collision on id, try next id.
			$nextId++;
			$tries++;
		}
		if (!$createdOk) {
			$errors[] = 'No se pudo crear c_typent '.$code.': '.$ct->error;
		}
	}

	return array('ok' => empty($errors), 'ids' => $ids, 'created' => $created, 'errors' => $errors);
}

/**
 * @param string $stmt
 * @return array{columns:string[],rows:array<int,array<int,mixed>>}|null
 */
function psv_parse_insert_stmt($stmt)
{
	// We only support INSERT INTO `tercero` (...) VALUES (...),(...);
	$posValues = stripos($stmt, 'VALUES');
	if ($posValues === false) return null;

	// Find columns list: first '(' after table name, up to ')'
	$firstParen = strpos($stmt, '(');
	if ($firstParen === false || $firstParen > $posValues) return null;
	$closeParen = strpos($stmt, ')', $firstParen);
	if ($closeParen === false || $closeParen > $posValues) return null;

	$colsRaw = substr($stmt, $firstParen + 1, $closeParen - $firstParen - 1);
	$cols = array();
	foreach (explode(',', $colsRaw) as $col) {
		$col = trim($col);
		$col = trim($col, "` \t\n\r\0\x0B");
		if ($col !== '') $cols[] = $col;
	}

	$valuesPart = substr($stmt, $posValues + 6);
	$valuesPart = trim($valuesPart);
	$valuesPart = rtrim($valuesPart, "; \t\n\r\0\x0B");

	$rows = array();
	$len = strlen($valuesPart);
	$inString = false;
	$escape = false;
	$depth = 0;
	$currentField = '';
	$currentRow = array();

	for ($i = 0; $i < $len; $i++) {
		$ch = $valuesPart[$i];

		if ($inString) {
			if ($escape) {
				$currentField .= $ch;
				$escape = false;
				continue;
			}
			if ($ch === '\\') {
				$escape = true;
				continue;
			}
			if ($ch === "'") {
				// Support doubled quotes inside strings: '' -> '
				if ($i + 1 < $len && $valuesPart[$i + 1] === "'") {
					$currentField .= "'";
					$i++;
					continue;
				}
				$inString = false;
				continue;
			}
			$currentField .= $ch;
			continue;
		}

		if ($ch === "'") {
			$inString = true;
			continue;
		}

		if ($ch === '(') {
			$depth++;
			if ($depth === 1) {
				$currentRow = array();
				$currentField = '';
			}
			continue;
		}

		if ($ch === ')') {
			if ($depth === 1) {
				// end of row
				$currentRow[] = psv_sql_value_to_php(trim($currentField));
				$rows[] = $currentRow;
				$currentField = '';
				$currentRow = array();
			}
			$depth--;
			continue;
		}

		if ($depth === 1 && $ch === ',') {
			// field separator or row separator (row separator handled by ')')
			$currentRow[] = psv_sql_value_to_php(trim($currentField));
			$currentField = '';
			continue;
		}

		if ($depth >= 1) {
			// accumulate raw token
			if (!ctype_space($ch) || $currentField !== '') {
				$currentField .= $ch;
			}
		}
	}

	return array('columns' => $cols, 'rows' => $rows);
}

/**
 * @param string $token
 * @return mixed
 */
function psv_sql_value_to_php($token)
{
	$token = trim($token);
	if ($token === '' || strtoupper($token) === 'NULL') return null;
	// numbers
	if (preg_match('/^-?[0-9]+$/', $token)) return (int) $token;
	if (preg_match('/^-?[0-9]+\.[0-9]+$/', $token)) return (float) $token;
	return $token;
}

/**
 * Read file handle and yield complete INSERT statements for table `tercero`.
 *
 * @param resource $handle
 * @return Generator<string>
 */
function psv_iter_tercero_insert_statements($handle)
{
	$buffer = '';
	$collecting = false;

	while (!feof($handle)) {
		$line = fgets($handle);
		if ($line === false) break;

		if (!$collecting) {
			if (stripos($line, 'INSERT INTO `tercero`') !== false || stripos($line, 'INSERT INTO tercero') !== false) {
				$collecting = true;
				$buffer = $line;
			} else {
				continue;
			}
		} else {
			$buffer .= $line;
		}

		if ($collecting && strpos($line, ';') !== false) {
			$collecting = false;
			yield $buffer;
			$buffer = '';
		}
	}
}

/**
 * Read file handle and yield complete INSERT statements for table `vivienda`.
 *
 * @param resource $handle
 * @return Generator<string>
 */
function psv_iter_vivienda_insert_statements($handle)
{
	$buffer = '';
	$collecting = false;

	while (!feof($handle)) {
		$line = fgets($handle);
		if ($line === false) break;

		if (!$collecting) {
			if (stripos($line, 'INSERT INTO `vivienda`') !== false || stripos($line, 'INSERT INTO vivienda') !== false) {
				$collecting = true;
				$buffer = $line;
			} else {
				continue;
			}
		} else {
			$buffer .= $line;
		}

		if ($collecting && strpos($line, ';') !== false) {
			$collecting = false;
			yield $buffer;
			$buffer = '';
		}
	}
}

/**
 * Read file handle and yield complete INSERT statements for table `contratoUsuario`.
 *
 * @param resource $handle
 * @return Generator<string>
 */
function psv_iter_contrato_usuario_insert_statements($handle)
{
	$buffer = '';
	$collecting = false;

	while (!feof($handle)) {
		$line = fgets($handle);
		if ($line === false) break;

		if (!$collecting) {
			if (stripos($line, 'INSERT INTO `contratoUsuario`') !== false || stripos($line, 'INSERT INTO contratoUsuario') !== false) {
				$collecting = true;
				$buffer = $line;
			} else {
				continue;
			}
		} else {
			$buffer .= $line;
		}

		if ($collecting && strpos($line, ';') !== false) {
			$collecting = false;
			yield $buffer;
			$buffer = '';
		}
	}
}

/**
 * Find an existing third party by PSV origin id (extrafield).
 *
 * @param DoliDB $db
 * @param int $sourceId
 * @return int|null
 */
function psv_find_societe_by_source_id($db, $sourceId)
{
	$sql = "SELECT s.rowid as id";
	$sql .= " FROM ".MAIN_DB_PREFIX."societe as s";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."societe_extrafields as ef ON ef.fk_object = s.rowid";
	$sql .= " WHERE ef.psv_id_origen_tercero = ".((int) $sourceId);
	$sql .= " LIMIT 1";

	$resql = $db->query($sql);
	if (!$resql) return null;
	$obj = $db->fetch_object($resql);
	if ($obj && !empty($obj->id)) return (int) $obj->id;
	return null;
}

/**
 * Find an existing project by PSV origin vivienda id (extrafield).
 *
 * @param DoliDB $db
 * @param int $sourceId
 * @return int|null
 */
function psv_find_project_by_source_id($db, $sourceId)
{
	global $conf;
	$sql = "SELECT p.rowid as id";
	$sql .= " FROM ".MAIN_DB_PREFIX."projet as p";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."projet_extrafields as ef ON ef.fk_object = p.rowid";
	$sql .= " WHERE p.entity = ".((int) $conf->entity);
	$sql .= " AND ef.psv_id_origen_vivienda = ".((int) $sourceId);
	$sql .= " LIMIT 1";

	$resql = $db->query($sql);
	if (!$resql) return null;
	$obj = $db->fetch_object($resql);
	if ($obj && !empty($obj->id)) return (int) $obj->id;
	return null;
}

/**
 * Find an existing project by project ref.
 *
 * @param DoliDB $db
 * @param string $ref
 * @return int|null
 */
function psv_find_project_by_ref($db, $ref)
{
	global $conf;
	$ref = trim($ref);
	if ($ref === '') return null;

	$sql = "SELECT rowid as id FROM ".MAIN_DB_PREFIX."projet";
	$sql .= " WHERE entity = ".((int) $conf->entity);
	$sql .= " AND ref = '".$db->escape($ref)."'";
	$sql .= " LIMIT 1";
	$resql = $db->query($sql);
	if (!$resql) return null;
	$obj = $db->fetch_object($resql);
	if ($obj && !empty($obj->id)) return (int) $obj->id;
	return null;
}

/**
 * Find an existing contract by PSV origin id (extrafield).
 *
 * @param DoliDB $db
 * @param int $sourceId
 * @return int|null
 */
function psv_find_contrat_by_source_id($db, $sourceId)
{
	global $conf;
	$sql = "SELECT c.rowid as id";
	$sql .= " FROM ".MAIN_DB_PREFIX."contrat as c";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."contrat_extrafields as ef ON ef.fk_object = c.rowid";
	$sql .= " WHERE c.entity = ".((int) $conf->entity);
	$sql .= " AND ef.psv_id_origen_contrato_usuario = ".((int) $sourceId);
	$sql .= " LIMIT 1";
	$resql = $db->query($sql);
	if (!$resql) return null;
	$obj = $db->fetch_object($resql);
	if ($obj && !empty($obj->id)) return (int) $obj->id;
	return null;
}

/**
 * Find an existing contract by ref_ext.
 *
 * @param DoliDB $db
 * @param string $refExt
 * @return int|null
 */
function psv_find_contrat_by_ref_ext($db, $refExt)
{
	global $conf;
	$refExt = trim($refExt);
	if ($refExt === '') return null;
	$sql = "SELECT rowid as id FROM ".MAIN_DB_PREFIX."contrat";
	$sql .= " WHERE entity = ".((int) $conf->entity);
	$sql .= " AND ref_ext = '".$db->escape($refExt)."'";
	$sql .= " LIMIT 1";
	$resql = $db->query($sql);
	if (!$resql) return null;
	$obj = $db->fetch_object($resql);
	if ($obj && !empty($obj->id)) return (int) $obj->id;
	return null;
}

/**
 * Find an existing contact person (socpeople) for a thirdparty by email.
 *
 * @param DoliDB $db
 * @param int $socid
 * @param string $email
 * @return int|null
 */
function psv_find_socpeople_by_socid_email($db, $socid, $email)
{
	global $conf;
	$email = trim($email);
	if ($socid <= 0 || $email === '') return null;

	$sql = "SELECT rowid as id";
	$sql .= " FROM ".MAIN_DB_PREFIX."socpeople";
	$sql .= " WHERE entity = ".((int) $conf->entity);
	$sql .= " AND fk_soc = ".((int) $socid);
	$sql .= " AND email = '".$db->escape($email)."'";
	$sql .= " LIMIT 1";
	$resql = $db->query($sql);
	if (!$resql) return null;
	$obj = $db->fetch_object($resql);
	if ($obj && !empty($obj->id)) return (int) $obj->id;
	return null;
}

/**
 * Find an existing contact person (socpeople) for a thirdparty by name.
 *
 * @param DoliDB $db
 * @param int $socid
 * @param string $firstname
 * @param string $lastname
 * @return int|null
 */
function psv_find_socpeople_by_socid_name($db, $socid, $firstname, $lastname)
{
	global $conf;
	$firstname = trim((string) $firstname);
	$lastname = trim((string) $lastname);
	if ($socid <= 0 || ($firstname === '' && $lastname === '')) return null;

	$sql = "SELECT rowid as id";
	$sql .= " FROM ".MAIN_DB_PREFIX."socpeople";
	$sql .= " WHERE entity = ".((int) $conf->entity);
	$sql .= " AND fk_soc = ".((int) $socid);
	if ($firstname !== '') {
		$sql .= " AND firstname = '".$db->escape($firstname)."'";
	}
	if ($lastname !== '') {
		$sql .= " AND lastname = '".$db->escape($lastname)."'";
	}
	$sql .= " LIMIT 1";
	$resql = $db->query($sql);
	if (!$resql) return null;
	$obj = $db->fetch_object($resql);
	if ($obj && !empty($obj->id)) return (int) $obj->id;
	return null;
}

/**
 * Find an existing contact person (socpeople) by external reference.
 *
 * @param DoliDB $db
 * @param string $refExt
 * @return int|null
 */
function psv_find_socpeople_by_ref_ext($db, $refExt)
{
	global $conf;
	$refExt = trim($refExt);
	if ($refExt === '') return null;

	$sql = "SELECT rowid as id";
	$sql .= " FROM ".MAIN_DB_PREFIX."socpeople";
	$sql .= " WHERE entity = ".((int) $conf->entity);
	$sql .= " AND ref_ext = '".$db->escape($refExt)."'";
	$sql .= " LIMIT 1";
	$resql = $db->query($sql);
	if (!$resql) return null;
	$obj = $db->fetch_object($resql);
	if ($obj && !empty($obj->id)) return (int) $obj->id;
	return null;
}

/**
 * Find an existing third party by NIF (stored in idprof1).
 *
 * @param DoliDB $db
 * @param string $nif
 * @return int|null
 */
function psv_find_societe_by_nif($db, $nif)
{
	$nif = trim($nif);
	if ($nif === '') return null;

	$sql = "SELECT rowid as id FROM ".MAIN_DB_PREFIX."societe";
	$sql .= " WHERE idprof1 = '".$db->escape($nif)."'";
	$sql .= " LIMIT 1";
	$resql = $db->query($sql);
	if (!$resql) return null;
	$obj = $db->fetch_object($resql);
	if ($obj && !empty($obj->id)) return (int) $obj->id;
	return null;
}

/**
 * Find an existing third party by email.
 *
 * @param DoliDB $db
 * @param string $email
 * @return int|null
 */
function psv_find_societe_by_email($db, $email)
{
	$email = trim($email);
	if ($email === '') return null;

	$sql = "SELECT rowid as id FROM ".MAIN_DB_PREFIX."societe";
	$sql .= " WHERE email = '".$db->escape($email)."'";
	$sql .= " LIMIT 1";
	$resql = $db->query($sql);
	if (!$resql) return null;
	$obj = $db->fetch_object($resql);
	if ($obj && !empty($obj->id)) return (int) $obj->id;
	return null;
}

/**
 * Upsert a third-party bank account (RIB) using IBAN.
 *
 * @param DoliDB $db
 * @param User $user
 * @param int $socid
 * @param string $iban
 * @param string $label
 * @return int >0 if OK, <0 if KO
 */
function psv_upsert_societe_rib_by_iban($db, $user, $socid, $iban, $label)
{
	$sql = "SELECT rowid";
	$sql .= " FROM ".MAIN_DB_PREFIX."societe_rib";
	$sql .= " WHERE fk_soc = ".((int) $socid);
	$sql .= " AND iban_prefix = '".$db->escape($iban)."'";
	$sql .= " LIMIT 1";
	$resql = $db->query($sql);
	if (!$resql) return -1;
	$obj = $db->fetch_object($resql);

	$rib = new CompanyBankAccount($db);
	if ($obj && !empty($obj->rowid)) {
		$rib->fetch((int) $obj->rowid);
	} else {
		$rib->socid = (int) $socid;
		$rib->type = 'ban';
		$rid = $rib->create($user);
		if ($rid <= 0) {
			return -1;
		}
		$rib->fetch($rid);
	}

	$rib->iban = $iban;
	$rib->label = $label;
	return $rib->update($user);
}

/**
 * Get the first RIB (bank account) for a third party.
 *
 * @param DoliDB $db
 * @param int $socid
 * @return int|null RIB ID or null
 */
function psv_get_first_rib_for_societe($db, $socid)
{
	$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe_rib";
	$sql .= " WHERE fk_soc = ".((int) $socid);
	$sql .= " ORDER BY rowid ASC LIMIT 1";
	$resql = $db->query($sql);
	if (!$resql) return null;
	$obj = $db->fetch_object($resql);
	if ($obj && !empty($obj->rowid)) return (int) $obj->rowid;
	return null;
}


llxHeader('', 'Migración PuertaSevilla');

print load_fiche_titre('Migración PuertaSevilla → Dolibarr (Terceros + Viviendas + Contratos)', '', 'project');

print '<div class="opacitymedium">';
print 'Esta herramienta importa <strong>terceros</strong> (tabla <code>tercero</code>), <strong>viviendas</strong> como <strong>proyectos</strong> (tabla <code>vivienda</code>) y <strong>contratos</strong> (tabla <code>contratoUsuario</code>) desde un SQL exportado.<br>';
print 'Los IBAN se guardan en la sección de <strong>Datos bancarios (RIB)</strong> del tercero.';
print '</div><br>';

// Clean action (can be executed independently)
if ($action === 'clean') {
	$db->begin();
	$cleanErrors = array();
	$cleanCounts = array('contratos' => 0, 'contactos' => 0, 'proyectos' => 0, 'terceros' => 0, 'extrafields' => 0);
	
	// Delete contracts
	$sqlClean = "DELETE FROM ".MAIN_DB_PREFIX."contrat WHERE ref_ext LIKE 'PSV-CU%'";
	$resClean = $db->query($sqlClean);
	if ($resClean) {
		$cleanCounts['contratos'] = $db->affected_rows($resClean);
	} else {
		$cleanErrors[] = 'Error limpiando contratos: '.$db->lasterror();
	}
	
	// Delete contacts (inquilinos)
	$sqlClean = "DELETE FROM ".MAIN_DB_PREFIX."socpeople WHERE ref_ext LIKE 'PSV-INQ-%'";
	$resClean = $db->query($sqlClean);
	if ($resClean) {
		$cleanCounts['contactos'] = $db->affected_rows($resClean);
	} else {
		$cleanErrors[] = 'Error limpiando contactos: '.$db->lasterror();
	}
	
	// Delete projects (viviendas)
	$sqlClean = "DELETE FROM ".MAIN_DB_PREFIX."projet WHERE ref LIKE 'PSV-VIV%'";
	$resClean = $db->query($sqlClean);
	if ($resClean) {
		$cleanCounts['proyectos'] = $db->affected_rows($resClean);
	} else {
		$cleanErrors[] = 'Error limpiando proyectos: '.$db->lasterror();
	}
	
	// Delete bank accounts (RIBs) of PSV third parties FIRST
	$sqlRibIds = "SELECT sr.rowid FROM ".MAIN_DB_PREFIX."societe_rib sr INNER JOIN ".MAIN_DB_PREFIX."societe s ON sr.fk_soc = s.rowid INNER JOIN ".MAIN_DB_PREFIX."societe_extrafields se ON s.rowid = se.fk_object WHERE se.psv_id_origen_tercero IS NOT NULL";
	$resRibIds = $db->query($sqlRibIds);
	if ($resRibIds) {
		while ($objRib = $db->fetch_object($resRibIds)) {
			$sqlDeleteRib = "DELETE FROM ".MAIN_DB_PREFIX."societe_rib WHERE rowid = ".((int) $objRib->rowid);
			$db->query($sqlDeleteRib);
		}
	}
	
	// Delete third parties with PSV origin
	$sqlClean = "DELETE s FROM ".MAIN_DB_PREFIX."societe s INNER JOIN ".MAIN_DB_PREFIX."societe_extrafields se ON s.rowid = se.fk_object WHERE se.psv_id_origen_tercero IS NOT NULL";
	$resClean = $db->query($sqlClean);
	if ($resClean) {
		$cleanCounts['terceros'] = $db->affected_rows($resClean);
	} else {
		$cleanErrors[] = 'Error limpiando terceros: '.$db->lasterror();
	}
	
	// Delete PSV extrafields that were created during migration
	$extrafields = new ExtraFields($db);
	$psvExtraFieldsToDelete = array(
		'psv_id_origen_tercero',
		'psv_nacionalidad',
		'psv_forma_pago_origen',
		'psv_id_origen_vivienda',
		'psv_ref_vivienda',
		'psv_direccion',
		'psv_localidad',
		'psv_propietario_id_origen',
		'psv_superficie',
		'psv_bagno',
		'psv_dormitorio',
		'psv_suministro',
		'psv_catastro',
		'psv_certificado',
		'psv_color',
		'psv_estado',
		'psv_compania',
		'psv_ncontrato',
		'psv_nombre_compania',
		'psv_id_origen_contrato_usuario',
		'psv_vivienda_id_origen',
		'psv_inquilino_id_origen',
		'psv_inquilino2_id_origen',
		'psv_finicio_origen',
		'psv_ffin_origen',
		'psv_periodo_origen',
		'psv_importe_origen',
		'psv_iva_origen',
		'psv_dia_pago',
		'psv_vigente',
		'psv_ccc_origen',
		'psv_entidad_ccc_origen',
		'psv_fk_rib',
	);
	
	foreach ($psvExtraFieldsToDelete as $attrname) {
		$sqlDeleteEF = "DELETE FROM ".MAIN_DB_PREFIX."extra_fields WHERE attrname = '".$db->escape($attrname)."'";
		$resDeleteEF = $db->query($sqlDeleteEF);
		if ($resDeleteEF) {
			$cleanCounts['extrafields'] += $db->affected_rows($resDeleteEF);
		} else {
			$cleanErrors[] = 'Error eliminando campo extra '.$attrname.': '.$db->lasterror();
		}
	}
	
	if (empty($cleanErrors)) {
		$db->commit();
		$msg = 'Datos eliminados correctamente: '.$cleanCounts['terceros'].' terceros, '.$cleanCounts['proyectos'].' proyectos, '.$cleanCounts['contratos'].' contratos, '.$cleanCounts['contactos'].' contactos, '.$cleanCounts['extrafields'].' campos extras.';
		setEventMessages($msg, null, 'mesgs');
	} else {
		$db->rollback();
		setEventMessages('Error limpiando datos anteriores', $cleanErrors, 'errors');
	}
}

if ($action === 'import') {
	// CSRF token is already checked by main.inc.php when MAIN_SECURITY_CSRF_WITH_TOKEN is enabled.
	$cleanBefore = GETPOST('clean_before', 'int');
	if ($cleanBefore == 1) {
		// Clean existing migrated data before importing
		$db->begin();
		$cleanErrors = array();
		// Delete contracts
		$sqlClean = "DELETE FROM ".MAIN_DB_PREFIX."contrat WHERE ref_ext LIKE 'PSV-CU%'";
		if (!$db->query($sqlClean)) $cleanErrors[] = 'Error limpiando contratos: '.$db->lasterror();
		// Delete contacts (inquilinos)
		$sqlClean = "DELETE FROM ".MAIN_DB_PREFIX."socpeople WHERE ref_ext LIKE 'PSV-INQ-%'";
		if (!$db->query($sqlClean)) $cleanErrors[] = 'Error limpiando contactos: '.$db->lasterror();
		// Delete projects (viviendas)
		$sqlClean = "DELETE FROM ".MAIN_DB_PREFIX."projet WHERE ref LIKE 'PSV-VIV%'";
		if (!$db->query($sqlClean)) $cleanErrors[] = 'Error limpiando proyectos: '.$db->lasterror();
		// Delete bank accounts (RIBs) of PSV third parties FIRST
		$sqlRibIds = "SELECT sr.rowid FROM ".MAIN_DB_PREFIX."societe_rib sr INNER JOIN ".MAIN_DB_PREFIX."societe s ON sr.fk_soc = s.rowid INNER JOIN ".MAIN_DB_PREFIX."societe_extrafields se ON s.rowid = se.fk_object WHERE se.psv_id_origen_tercero IS NOT NULL";
		$resRibIds = $db->query($sqlRibIds);
		if ($resRibIds) {
			while ($objRib = $db->fetch_object($resRibIds)) {
				$sqlDeleteRib = "DELETE FROM ".MAIN_DB_PREFIX."societe_rib WHERE rowid = ".((int) $objRib->rowid);
				$db->query($sqlDeleteRib);
			}
		}
		// Delete third parties with PSV origin
		$sqlClean = "DELETE s FROM ".MAIN_DB_PREFIX."societe s INNER JOIN ".MAIN_DB_PREFIX."societe_extrafields se ON s.rowid = se.fk_object WHERE se.psv_id_origen_tercero IS NOT NULL";
		if (!$db->query($sqlClean)) $cleanErrors[] = 'Error limpiando terceros: '.$db->lasterror();
		// Delete PSV extrafields
		$psvExtraFieldsToDelete = array(
			'psv_id_origen_tercero',
			'psv_nacionalidad',
			'psv_forma_pago_origen',
			'psv_id_origen_vivienda',
			'psv_ref_vivienda',
			'psv_direccion',
			'psv_localidad',
			'psv_propietario_id_origen',
			'psv_superficie',
			'psv_bagno',
			'psv_dormitorio',
			'psv_suministro',
			'psv_catastro',
			'psv_certificado',
			'psv_color',
			'psv_estado',
			'psv_compania',
			'psv_ncontrato',
			'psv_nombre_compania',
			'psv_id_origen_contrato_usuario',
			'psv_vivienda_id_origen',
			'psv_inquilino_id_origen',
			'psv_inquilino2_id_origen',
			'psv_finicio_origen',
			'psv_ffin_origen',
			'psv_periodo_origen',
			'psv_importe_origen',
			'psv_iva_origen',
			'psv_dia_pago',
			'psv_vigente',
			'psv_ccc_origen',
			'psv_entidad_ccc_origen',
			'psv_fk_rib',
		);
		foreach ($psvExtraFieldsToDelete as $attrname) {
			$sqlDeleteEF = "DELETE FROM ".MAIN_DB_PREFIX."extra_fields WHERE attrname = '".$db->escape($attrname)."'";
			$db->query($sqlDeleteEF);
		}
		if (empty($cleanErrors)) {
			$db->commit();
			setEventMessages('Datos anteriores eliminados correctamente.', null, 'mesgs');
		} else {
			$db->rollback();
			setEventMessages('Error limpiando datos anteriores', $cleanErrors, 'errors');
		}
	}
	$ensureExtra = psv_ensure_societe_extrafields($db);
	$ensureProjetExtra = psv_ensure_projet_extrafields($db);
	$ensureTypent = psv_ensure_typent_roles($db, $user);
	$ensureContactType = psv_ensure_project_contact_type_inquilino($db);
	$ensureContratExtra = psv_ensure_contrat_extrafields($db);
	if (!$ensureExtra['ok'] || !$ensureProjetExtra['ok'] || !$ensureTypent['ok'] || !$ensureContactType['ok'] || !$ensureContratExtra['ok']) {
		if (!$ensureExtra['ok']) {
			setEventMessages('Error creando campos extra necesarios', $ensureExtra['errors'], 'errors');
		}
		if (!$ensureProjetExtra['ok']) {
			setEventMessages('Error creando campos extra necesarios (proyectos/viviendas)', $ensureProjetExtra['errors'], 'errors');
		}
		if (!$ensureTypent['ok']) {
			setEventMessages('Error preparando diccionario de tipo de tercero (rol)', $ensureTypent['errors'], 'errors');
		}
		if (!$ensureContactType['ok']) {
			setEventMessages('Error preparando tipo de contacto INQUILINO para proyectos', $ensureContactType['errors'], 'errors');
		}
		if (!$ensureContratExtra['ok']) {
			setEventMessages('Error creando campos extra necesarios (contratos)', $ensureContratExtra['errors'], 'errors');
		}
	} else {
		if (empty($_FILES['sqlfile']['tmp_name']) || !is_uploaded_file($_FILES['sqlfile']['tmp_name'])) {
			setEventMessages('No se ha subido ningún archivo válido.', null, 'errors');
		} else {
			$roleTypentIds = $ensureTypent['ids'];
			$sourceSocIdMap = array(); // source tercero id -> socid
			$sourceSocContactData = array(); // source tercero id -> minimal info to create a contact

			$tmp = $_FILES['sqlfile']['tmp_name'];
			$handle = fopen($tmp, 'r');
			if (!$handle) {
				setEventMessages('No se pudo abrir el archivo subido.', null, 'errors');
			} else {
				$created = 0;
				$updated = 0;
				$skipped = 0;
				$errors = 0;
				$errorMessages = array();
				$warnings = 0;
				$warningMessages = array();
				$projectsCreated = 0;
				$projectsUpdated = 0;
				$projectsSkipped = 0;
				$projectsErrors = 0;
				$contractsCreated = 0;
				$contractsUpdated = 0;
				$contractsSkipped = 0;
				$contractsErrors = 0;
				$contactsAdded = 0;
				$contactsSkipped = 0;
				$contactsErrors = 0;

				foreach (psv_iter_tercero_insert_statements($handle) as $stmt) {
					$parsed = psv_parse_insert_stmt($stmt);
					if ($parsed === null) continue;

					$cols = $parsed['columns'];
					foreach ($parsed['rows'] as $row) {
						if (count($row) !== count($cols)) {
							$errors++;
							$errorMessages[] = 'Fila con número de columnas inesperado (se omite).';
							continue;
						}

						$data = array();
						foreach ($cols as $idx => $colname) {
							$data[$colname] = $row[$idx];
						}

						$sourceId = isset($data['id']) ? (int) $data['id'] : 0;
						if ($sourceId <= 0) {
							$skipped++;
							continue;
						}

						$nombre = psv_nullif_empty($data['nombre'] ?? null);
						$apellido1 = psv_nullif_empty($data['apellido1'] ?? null);
						$apellido2 = psv_nullif_empty($data['apellido2'] ?? null);
						$nacionalidad = psv_nullif_empty($data['nacionalidad'] ?? null);
						$direccion = psv_nullif_empty($data['direccion'] ?? null);
						$localidad = psv_nullif_empty($data['localidad'] ?? null);
						$email = psv_nullif_empty($data['email'] ?? null);
						$nif = psv_nullif_empty($data['nif'] ?? null);
						$telefono = psv_nullif_empty($data['telefono'] ?? null);
						$formaPago = psv_nullif_empty($data['formaPago'] ?? null);
						$rolId = isset($data['rol_id']) ? (int) $data['rol_id'] : 0;

							$rol = null;
							$typentIdForRole = 0;
							if ($rolId === 1) {
								$rol = 'administrador';
								$typentIdForRole = !empty($roleTypentIds['PSV_ADMIN']) ? (int) $roleTypentIds['PSV_ADMIN'] : 0;
							} elseif ($rolId === 2) {
								$rol = 'propietario';
								$typentIdForRole = !empty($roleTypentIds['PSV_PROP']) ? (int) $roleTypentIds['PSV_PROP'] : 0;
							} elseif ($rolId === 3) {
								$rol = 'inquilino';
								$typentIdForRole = !empty($roleTypentIds['PSV_INQ']) ? (int) $roleTypentIds['PSV_INQ'] : 0;
							}

						$hasRealName = ($nombre !== null || $apellido1 !== null || $apellido2 !== null);
						$displayName = trim((string) $nombre.' '.(string) $apellido1.' '.(string) $apellido2);
						if ($displayName === '') {
							$displayName = 'Tercero '.$sourceId;
						}

						// Find existing
						$socid = psv_find_societe_by_source_id($db, $sourceId);
						if (!$socid && $nif) $socid = psv_find_societe_by_nif($db, $nif);
						if (!$socid && $email) $socid = psv_find_societe_by_email($db, $email);

						$soc = new Societe($db);
						$isUpdate = false;
						if ($socid) {
							$soc->fetch($socid);
							$isUpdate = true;
						}

						// Assign fields (only overwrite with non-empty values)
						if (!$isUpdate) {
							$soc->name = $displayName;
						} elseif ($hasRealName) {
							$soc->name = $displayName;
						}
						if ($direccion !== null) $soc->address = $direccion;
						if ($localidad !== null) $soc->town = $localidad;
						if ($email !== null) $soc->email = $email;
						if ($telefono !== null) $soc->phone = $telefono;
						if ($nif !== null) $soc->idprof1 = $nif;
							if ($typentIdForRole > 0) {
								$soc->typent_id = $typentIdForRole;
							}

						// Customer flag for inquilinos (will receive invoices)
						if ($rol === 'inquilino') {
							$soc->client = 1;
						}

						$soc->array_options['options_psv_id_origen_tercero'] = $sourceId;
						if ($nacionalidad !== null) $soc->array_options['options_psv_nacionalidad'] = $nacionalidad;
						if ($formaPago !== null) $soc->array_options['options_psv_forma_pago_origen'] = $formaPago;

						$res = 0;
						if ($isUpdate) {
							$res = $soc->update($soc->id, $user);
							if ($res > 0) $updated++;
						} else {
							$res = $soc->create($user);
							if ($res > 0) {
								$created++;
								$socid = $res;
							}
						}

						if ($res <= 0) {
							$errors++;
							$errorMessages[] = 'Error importando tercero '.$sourceId.': '.$soc->error;
							continue;
						}

						// Track mapping for later vivienda->propietario linking
						if ($socid > 0) {
							$sourceSocIdMap[$sourceId] = (int) $socid;
						}

						// Store minimal data to build a contact person later
						$sourceSocContactData[$sourceId] = array(
							'firstname' => ($nombre !== null ? $nombre : ''),
							'lastname' => trim((string) ($apellido1 !== null ? $apellido1 : '').' '.(string) ($apellido2 !== null ? $apellido2 : '')),
							'email' => ($email !== null ? $email : ''),
							'phone' => ($telefono !== null ? $telefono : ''),
							'display' => $displayName,
						);

						// IBANs -> RIB
							$iban1 = psv_normalize_iban($data['iban'] ?? null);
							$iban2 = psv_normalize_iban($data['iban2'] ?? null);
						if ($iban1) {
							$rr = psv_upsert_societe_rib_by_iban($db, $user, $socid, $iban1, 'IBAN (PuertaSevilla)');
							if ($rr <= 0) {
								$errors++;
								$errorMessages[] = 'No se pudo guardar IBAN para tercero '.$sourceId;
							}
						}
						if ($iban2 && $iban2 !== $iban1) {
							$rr2 = psv_upsert_societe_rib_by_iban($db, $user, $socid, $iban2, 'IBAN2 (PuertaSevilla)');
							if ($rr2 <= 0) {
								$errors++;
								$errorMessages[] = 'No se pudo guardar IBAN2 para tercero '.$sourceId;
							}
						}
					}
				}

				fclose($handle);

				// Second pass: viviendas -> projects
				$handle2 = fopen($tmp, 'r');
				if (!$handle2) {
					$projectsErrors++;
					$errorMessages[] = 'No se pudo reabrir el archivo para importar viviendas.';
				} else {
					foreach (psv_iter_vivienda_insert_statements($handle2) as $stmt2) {
						$parsed2 = psv_parse_insert_stmt($stmt2);
						if ($parsed2 === null) continue;

						$cols2 = $parsed2['columns'];
						foreach ($parsed2['rows'] as $row2) {
							if (count($row2) !== count($cols2)) {
								$projectsErrors++;
								$errorMessages[] = 'Vivienda: fila con número de columnas inesperado (se omite).';
								continue;
							}

							$data2 = array();
							foreach ($cols2 as $idx2 => $colname2) {
								$data2[$colname2] = $row2[$idx2];
							}

							$vivId = isset($data2['id']) ? (int) $data2['id'] : 0;
							if ($vivId <= 0) {
								$projectsSkipped++;
								continue;
							}

							$vivRef = psv_nullif_empty($data2['ref'] ?? null);
							$direccionViv = psv_nullif_empty($data2['direccion'] ?? null);
							$localidadViv = psv_nullif_empty($data2['localidad'] ?? null);
							$superficieViv = psv_nullif_empty($data2['superficie'] ?? null);
							$obsViv = psv_nullif_empty($data2['obs'] ?? null);
							$bagnoViv = psv_nullif_empty($data2['bagno'] ?? null);
							$dormitorioViv = psv_nullif_empty($data2['dormitorio'] ?? null);
							$suministroViv = psv_nullif_empty($data2['suministro'] ?? null);
							$catastroViv = psv_nullif_empty($data2['catastro'] ?? null);
							$certificadoViv = psv_nullif_empty($data2['certificado'] ?? null);
							$colorViv = psv_nullif_empty($data2['color'] ?? null);
							$estadoViv = isset($data2['estado']) ? (int) $data2['estado'] : 1;
							$propietarioOrigenId = isset($data2['propietario_id']) ? (int) $data2['propietario_id'] : 0;
							$companiaViv = psv_nullif_empty($data2['compañia'] ?? ($data2['compania'] ?? null));
							$ncontratoViv = psv_nullif_empty($data2['ncontrato'] ?? null);
							$nombreCompaniaViv = psv_nullif_empty($data2['nombreCompañia'] ?? ($data2['nombreCompania'] ?? null));

							$projectRef = 'PSV-VIV'.$vivId;
							$projectId = psv_find_project_by_source_id($db, $vivId);
							if (!$projectId) $projectId = psv_find_project_by_ref($db, $projectRef);

							$project = new Project($db);
							$isUpdateProj = false;
							if ($projectId) {
								$project->fetch($projectId);
								$isUpdateProj = true;
							}

							$titleBase = 'Vivienda '.($vivRef !== null ? $vivRef : $vivId);
							$title = $titleBase;
							if ($direccionViv !== null && trim($direccionViv) !== '') {
								$title = trim($titleBase.' - '.$direccionViv);
							}

							$project->ref = $projectRef;
							$project->title = $title;
							$project->description = ($obsViv !== null ? $obsViv : '');
							$project->location = ($direccionViv !== null ? $direccionViv : '');
							$project->statut = ($estadoViv === 1 ? 1 : 2);

							// Try to link owner as thirdparty (fk_soc)
							$ownerSocid = 0;
							if ($propietarioOrigenId > 0) {
								if (!empty($sourceSocIdMap[$propietarioOrigenId])) {
									$ownerSocid = (int) $sourceSocIdMap[$propietarioOrigenId];
								} else {
									$ownerSocid = (int) (psv_find_societe_by_source_id($db, $propietarioOrigenId) ?: 0);
								}
							}
							if ($ownerSocid > 0) {
								$project->socid = $ownerSocid;
							}

							$project->array_options['options_psv_id_origen_vivienda'] = $vivId;
							if ($vivRef !== null) $project->array_options['options_psv_ref_vivienda'] = $vivRef;
							if ($direccionViv !== null) $project->array_options['options_psv_direccion'] = $direccionViv;
							if ($localidadViv !== null) $project->array_options['options_psv_localidad'] = $localidadViv;
							if ($propietarioOrigenId > 0) $project->array_options['options_psv_propietario_id_origen'] = $propietarioOrigenId;
							if ($superficieViv !== null) $project->array_options['options_psv_superficie'] = $superficieViv;
							if ($bagnoViv !== null) $project->array_options['options_psv_bagno'] = $bagnoViv;
							if ($dormitorioViv !== null) $project->array_options['options_psv_dormitorio'] = $dormitorioViv;
							if ($suministroViv !== null) $project->array_options['options_psv_suministro'] = $suministroViv;
							if ($catastroViv !== null) $project->array_options['options_psv_catastro'] = $catastroViv;
							if ($certificadoViv !== null) $project->array_options['options_psv_certificado'] = $certificadoViv;
							if ($colorViv !== null) $project->array_options['options_psv_color'] = $colorViv;
							$project->array_options['options_psv_estado'] = $estadoViv;
							if ($companiaViv !== null) $project->array_options['options_psv_compania'] = $companiaViv;
							if ($ncontratoViv !== null) $project->array_options['options_psv_ncontrato'] = $ncontratoViv;
							if ($nombreCompaniaViv !== null) $project->array_options['options_psv_nombre_compania'] = $nombreCompaniaViv;

							$resProj = 0;
							if ($isUpdateProj) {
								$resProj = $project->update($user);
								if ($resProj > 0) $projectsUpdated++;
							} else {
								$resProj = $project->create($user);
								if ($resProj > 0) {
									$project->id = $resProj;
									$project->insertExtraFields();
									$projectsCreated++;
								}
							}

							if ($resProj <= 0) {
								$projectsErrors++;
								$errorMessages[] = 'Error importando vivienda '.$vivId.' como proyecto: '.$project->error;
								continue;
							}
						}
					}

					fclose($handle2);
				}

				// Third pass: contratoUsuario -> add inquilinos as contacts in the related project
				$handle3 = fopen($tmp, 'r');
				if (!$handle3) {
					$contactsErrors++;
					$errorMessages[] = 'No se pudo reabrir el archivo para importar contratos (inquilinos como contactos).';
				} else {
					foreach (psv_iter_contrato_usuario_insert_statements($handle3) as $stmt3) {
						$parsed3 = psv_parse_insert_stmt($stmt3);
						if ($parsed3 === null) continue;

						$cols3 = $parsed3['columns'];
						foreach ($parsed3['rows'] as $row3) {
							if (count($row3) !== count($cols3)) {
								$contactsErrors++;
								$errorMessages[] = 'ContratoUsuario: fila con número de columnas inesperado (se omite).';
								continue;
							}

							$data3 = array();
							foreach ($cols3 as $idx3 => $colname3) {
								$data3[$colname3] = $row3[$idx3];
							}

							$contratoUsuarioId = isset($data3['id']) ? (int) $data3['id'] : 0;
							$viviendaId = isset($data3['vivienda_id']) ? (int) $data3['vivienda_id'] : 0;
							if ($viviendaId <= 0) {
								$contractsSkipped++;
								$contactsSkipped++;
								continue;
							}
							if ($contratoUsuarioId <= 0) {
								$contractsSkipped++;
								continue;
							}

							$vigente = array_key_exists('vigente', $data3) ? (int) $data3['vigente'] : 1;

							$inquilino1 = isset($data3['inquilino_id']) ? (int) $data3['inquilino_id'] : 0;
							$inquilino2 = isset($data3['inquilino2']) ? (int) $data3['inquilino2'] : 0;
							$propietarioOrigenId = isset($data3['propietario_id']) ? (int) $data3['propietario_id'] : 0;
							$finicioRaw = psv_nullif_empty($data3['finicio'] ?? null);
							$ffinRaw = psv_nullif_empty($data3['ffin'] ?? null);
							$periodoRaw = psv_nullif_empty($data3['periodo'] ?? null);
							$importeRaw = psv_nullif_empty($data3['importe'] ?? null);
							$ivaRaw = psv_nullif_empty($data3['IVA'] ?? ($data3['iva'] ?? null));
							$diaPago = isset($data3['dia_pago']) ? (int) $data3['dia_pago'] : 0;
							$cccRaw = psv_nullif_empty($data3['ccc'] ?? null);
							$entidadCccRaw = psv_nullif_empty($data3['entidad_ccc'] ?? null);

							// Create/Update Dolibarr Contract (Contrat) for contratoUsuario
							$refExt = 'PSV-CU'.$contratoUsuarioId;
							$contractId = psv_find_contrat_by_source_id($db, $contratoUsuarioId);
							if (!$contractId) $contractId = psv_find_contrat_by_ref_ext($db, $refExt);

							$contract = new Contrat($db);
							$isUpdateContract = false;
							if ($contractId) {
								$contract->fetch($contractId);
								$isUpdateContract = true;
							}

							$projectId = psv_find_project_by_source_id($db, $viviendaId);
							if ($projectId) {
								$contract->fk_project = (int) $projectId;
							}

							// Contract thirdparty is the owner (propietario)
							$ownerSocid = ($propietarioOrigenId > 0 ? (int) (psv_find_societe_by_source_id($db, $propietarioOrigenId) ?: 0) : 0);
							$contractSocid = $ownerSocid;
							if ($contractSocid <= 0) {
								$contractsSkipped++;
								$warningMessages[] = 'ContratoUsuario '.$contratoUsuarioId.' sin tercero asociado (no se crea/actualiza contrato).';
							} else {
								$contract->socid = $contractSocid;
								$contract->commercial_signature_id = (int) $user->id;
								$contract->commercial_suivi_id = (int) $user->id;
								$contract->ref_ext = $refExt;
								$contract->date_contrat = psv_parse_date_ts($finicioRaw);
								$contract->note_private = 'Migrado desde PuertaSevilla (contratoUsuario '.$contratoUsuarioId.').';

								$contract->array_options['options_psv_id_origen_contrato_usuario'] = $contratoUsuarioId;
								$contract->array_options['options_psv_vivienda_id_origen'] = $viviendaId;
								if ($propietarioOrigenId > 0) $contract->array_options['options_psv_propietario_id_origen'] = $propietarioOrigenId;
								if ($inquilino1 > 0) $contract->array_options['options_psv_inquilino_id_origen'] = $inquilino1;
								if ($inquilino2 > 0) $contract->array_options['options_psv_inquilino2_id_origen'] = $inquilino2;
								$finicioTs = psv_parse_date_ts($finicioRaw);
							$ffinTs = psv_parse_date_ts($ffinRaw);
							if ($finicioTs > 0) $contract->array_options['options_psv_finicio_origen'] = $finicioTs;
							if ($ffinTs > 0) $contract->array_options['options_psv_ffin_origen'] = $ffinTs;
								if ($periodoRaw !== null) $contract->array_options['options_psv_periodo_origen'] = $periodoRaw;
								if ($importeRaw !== null) $contract->array_options['options_psv_importe_origen'] = $importeRaw;
								if ($ivaRaw !== null) $contract->array_options['options_psv_iva_origen'] = $ivaRaw;
								if ($diaPago > 0) $contract->array_options['options_psv_dia_pago'] = $diaPago;
								$contract->array_options['options_psv_vigente'] = $vigente;
								if ($cccRaw !== null) $contract->array_options['options_psv_ccc_origen'] = $cccRaw;
								if ($entidadCccRaw !== null) $contract->array_options['options_psv_entidad_ccc_origen'] = $entidadCccRaw;

							// Link first RIB of owner to contract
							$ownerRibId = psv_get_first_rib_for_societe($db, $contractSocid);
							if ($ownerRibId) {
								$contract->array_options['options_psv_fk_rib'] = $ownerRibId;
							}

							$resContract = 0;
								if ($isUpdateContract) {
									$resContract = $contract->update($user, 1);
									if ($resContract > 0) $contractsUpdated++;
								} else {
									$resContract = $contract->create($user);
									if ($resContract > 0) $contractsCreated++;
								}

								if ($resContract <= 0) {
								$contractsErrors++;
								$errorMessages[] = 'Error importando contratoUsuario '.$contratoUsuarioId.' como contrato: '.$contract->error;
							} else {
								// Contract created/updated successfully
								if (!$isUpdateContract) {
									$contract->fetch($resContract);
								}

								// Create contract line (service) with description and dates
								require_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
								$contractLineDesc = 'Alquiler vivienda ref '.$refExt;
								if ($periodoRaw) $contractLineDesc .= ' ('.dol_escape_htmltag($periodoRaw).')';
								$contractLinePrice = 0;
								if ($importeRaw && is_numeric($importeRaw)) $contractLinePrice = (float) $importeRaw;
								$contractLineVat = 0;
								if ($ivaRaw && is_numeric($ivaRaw)) $contractLineVat = (float) $ivaRaw;

								// Check if line already exists to avoid duplicates on update
								$sqlLine = "SELECT rowid FROM ".MAIN_DB_PREFIX."contratdet WHERE fk_contrat = ".((int) $contract->id)." LIMIT 1";
								$resqlLine = $db->query($sqlLine);
								$lineExists = ($resqlLine && $db->num_rows($resqlLine) > 0);

								if (!$lineExists) {
									$line = new ContratLigne($db);
									$line->fk_contrat = (int) $contract->id;
									$line->description = $contractLineDesc;
									$line->qty = 1;
									$line->subprice = $contractLinePrice;
									$line->tva_tx = $contractLineVat;
									$line->date_start = $finicioTs > 0 ? $finicioTs : 0;
									$line->date_end = $ffinTs > 0 ? $ffinTs : 0;
									$resLine = $line->insert($user);
									if ($resLine <= 0) {
										$errorMessages[] = 'Error creando línea de contrato para contratoUsuario '.$contratoUsuarioId.': '.($line->error ?: 'desconocido');
									}
								}

								// Link tenants as contacts to contract
								$tenantIds = array();
								if ($inquilino1 > 0) $tenantIds[] = $inquilino1;
								if ($inquilino2 > 0 && $inquilino2 !== $inquilino1) $tenantIds[] = $inquilino2;
								foreach ($tenantIds as $tenantSourceId) {
									$refExtTenant = 'PSV-INQ-'.$tenantSourceId;
									$contactId = psv_find_socpeople_by_ref_ext($db, $refExtTenant);
									if ($contactId) {
										// Add contact to contract using CUSTOMER role
										$contract->add_contact((int) $contactId, 'CUSTOMER', 'external');
									}
								}
							}
						}
						$tenantSourceIds = array();
							if ($inquilino1 > 0) $tenantSourceIds[] = $inquilino1;
							if ($inquilino2 > 0 && $inquilino2 !== $inquilino1) $tenantSourceIds[] = $inquilino2;
							if (empty($tenantSourceIds)) {
								$contactsSkipped++;
								continue;
							}

							// Only add contacts for current contracts
							if ($vigente != 1) {
								$contactsSkipped++;
								continue;
							}
							if (!$projectId) {
								$contactsSkipped++;
								continue;
							}

							$projectObj = new Project($db);
							$projectObj->fetch($projectId);

							foreach ($tenantSourceIds as $tenantSourceId) {
								$info = !empty($sourceSocContactData[$tenantSourceId]) ? $sourceSocContactData[$tenantSourceId] : array();
								$firstname = trim((string) ($info['firstname'] ?? ''));
								$lastname = trim((string) ($info['lastname'] ?? ''));
								$emailT = trim((string) ($info['email'] ?? ''));
								$phoneT = trim((string) ($info['phone'] ?? ''));
								$displayT = trim((string) ($info['display'] ?? ''));
								if ($lastname === '' && $displayT !== '') {
									$lastname = $displayT;
								}
								if ($lastname === '' && $firstname === '') {
									$lastname = 'Inquilino '.$tenantSourceId;
								}

								$refExtTenant = 'PSV-INQ-'.$tenantSourceId;
								$contactId = psv_find_socpeople_by_ref_ext($db, $refExtTenant);
								$c = new Contact($db);
								if ($contactId) {
									$c->fetch((int) $contactId);
									// Keep it unlinked to any thirdparty
									$c->socid = -1;
									if ($firstname !== '') $c->firstname = $firstname;
									if ($lastname !== '') $c->lastname = $lastname;
									if ($emailT !== '') $c->email = $emailT;
									if ($phoneT !== '') $c->phone_pro = $phoneT;
									$c->ref_ext = $refExtTenant;
									$c->statut = 1;
									$upd = $c->update((int) $contactId, $user, 1);
									if ($upd <= 0) {
										$contactsErrors++;
										$errorMessages[] = 'No se pudo actualizar contacto (inquilino) '.$tenantSourceId.': '.$c->error;
										continue;
									}
								} else {
									// Create standalone contact (no thirdparty)
									$c->socid = 0;
									$c->firstname = $firstname;
									$c->lastname = $lastname;
									$c->email = $emailT;
									$c->phone_pro = $phoneT;
									$c->ref_ext = $refExtTenant;
									$c->statut = 1;
									$newCid = $c->create($user);
									if ($newCid > 0) {
										$contactId = (int) $newCid;
									} else {
										$contactsErrors++;
										$errorMessages[] = 'No se pudo crear contacto (inquilino) '.$tenantSourceId.': '.$c->error;
										continue;
									}
								}

								// Add contact to project as tenant
								$addres = $projectObj->add_contact((int) $contactId, 'INQUILINO', 'external');
								if ($addres > 0) {
									$contactsAdded++;
								} elseif ($addres === 0) {
									$contactsSkipped++;
								} else {
									$contactsErrors++;
									$errorMessages[] = 'No se pudo enlazar contacto de inquilino '.$tenantSourceId.' en proyecto vivienda '.$viviendaId.': '.$projectObj->error;
								}
							}
						}
					}

					fclose($handle3);
				}

				$msg = 'Importación finalizada. Terceros creados: '.$created.', terceros actualizados: '.$updated.', terceros omitidos: '.$skipped.', proyectos creados: '.$projectsCreated.', proyectos actualizados: '.$projectsUpdated.', contratos creados: '.$contractsCreated.', contratos actualizados: '.$contractsUpdated.', contactos (inquilinos) añadidos: '.$contactsAdded.', errores: '.($errors + $projectsErrors + $contractsErrors + $contactsErrors);
				$allDetails = array_merge($warningMessages, $errorMessages);
				if (($errors + $projectsErrors + $contractsErrors + $contactsErrors) > 0) {
					setEventMessages($msg, array_slice($allDetails, 0, 50), 'warnings');
				} else {
					setEventMessages($msg, array_slice($allDetails, 0, 50), 'mesgs');
				}

				// Persist a human-readable report (notify) for the last run
				$_SESSION['psv_migracion_last_report'] = array(
					'ts' => dol_print_date(dol_now(), 'dayhourlog'),
					'file' => (!empty($_FILES['sqlfile']['name']) ? $_FILES['sqlfile']['name'] : ''),
					'created' => $created,
					'updated' => $updated,
					'skipped' => $skipped,
					'errors' => ($errors + $projectsErrors + $contractsErrors + $contactsErrors),
					'projectsCreated' => $projectsCreated,
					'projectsUpdated' => $projectsUpdated,
					'projectsSkipped' => $projectsSkipped,
					'projectsErrors' => $projectsErrors,
					'contractsCreated' => $contractsCreated,
					'contractsUpdated' => $contractsUpdated,
					'contractsSkipped' => $contractsSkipped,
					'contractsErrors' => $contractsErrors,
					'contactsAdded' => $contactsAdded,
					'contactsSkipped' => $contactsSkipped,
					'contactsErrors' => $contactsErrors,
					'details' => array_slice($allDetails, 0, 200),
					'typent' => $ensureTypent,
				);
			}
		}
	}
}

// Form for purge only
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" style="margin-bottom: 30px;">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="clean">';
print '<div class="fichecenter">';
print '<div class="info">Purgar todos los datos migrados de PuertaSevilla (terceros, proyectos, contratos y contactos) sin importar nuevos datos.</div><br>';
print '</div>';
print '<div class="center">';
print '<input class="button butActionDelete" type="submit" value="Purgar datos migrados" onclick="return confirm(\'¿Está seguro de que desea eliminar TODOS los datos migrados de PuertaSevilla?\');">';
print '</div>';
print '</form>';

print '<hr style="margin: 30px 0;">';

// Form for import
print '<form method="POST" enctype="multipart/form-data" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="import">';

print '<div class="fichecenter">';
print '<table class="border centpercent">';
print '<tr><td class="titlefield">SQL (dump)</td>';
print '<td><input type="file" name="sqlfile" accept=".sql" required></td></tr>';
print '<tr><td>Borrar datos anteriores</td>';
print '<td><input type="checkbox" name="clean_before" value="1"> Eliminar terceros, proyectos, contratos y contactos migrados anteriormente antes de importar</td></tr>';
print '</table>';
print '</div>';

print '<div class="center">';
print '<input class="button button-save" type="submit" value="Importar terceros + viviendas + contratos">';
print '</div>';

print '</form>';

// Notify/report block
if (!empty($_SESSION['psv_migracion_last_report']) && is_array($_SESSION['psv_migracion_last_report'])) {
	$r = $_SESSION['psv_migracion_last_report'];
	print '<br>';
	print load_fiche_titre('Estado de la última importación', '', 'info');
	print '<div class="fichecenter">';
	print '<table class="border centpercent">';
	print '<tr><td class="titlefield">Fecha</td><td>'.dol_escape_htmltag($r['ts'] ?? '').'</td></tr>';
	print '<tr><td>Archivo</td><td>'.dol_escape_htmltag($r['file'] ?? '').'</td></tr>';
	print '<tr><td>Terceros creados</td><td>'.((int) ($r['created'] ?? 0)).'</td></tr>';
	print '<tr><td>Terceros actualizados</td><td>'.((int) ($r['updated'] ?? 0)).'</td></tr>';
	print '<tr><td>Terceros omitidos</td><td>'.((int) ($r['skipped'] ?? 0)).'</td></tr>';
	print '<tr><td>Proyectos creados</td><td>'.((int) ($r['projectsCreated'] ?? 0)).'</td></tr>';
	print '<tr><td>Proyectos actualizados</td><td>'.((int) ($r['projectsUpdated'] ?? 0)).'</td></tr>';
	print '<tr><td>Proyectos omitidos</td><td>'.((int) ($r['projectsSkipped'] ?? 0)).'</td></tr>';
	print '<tr><td>Contratos creados</td><td>'.((int) ($r['contractsCreated'] ?? 0)).'</td></tr>';
	print '<tr><td>Contratos actualizados</td><td>'.((int) ($r['contractsUpdated'] ?? 0)).'</td></tr>';
	print '<tr><td>Contratos omitidos</td><td>'.((int) ($r['contractsSkipped'] ?? 0)).'</td></tr>';
	print '<tr><td>Contactos (inquilinos) añadidos</td><td>'.((int) ($r['contactsAdded'] ?? 0)).'</td></tr>';
	print '<tr><td>Errores</td><td>'.((int) ($r['errors'] ?? 0)).'</td></tr>';
	print '</table>';
	print '</div>';

	if (!empty($r['typent']['created']) && is_array($r['typent']['created'])) {
		$createdTypent = array();
		foreach ($r['typent']['created'] as $code => $wasCreated) {
			if ($wasCreated) $createdTypent[] = $code;
		}
		if (!empty($createdTypent)) {
			print '<br><div class="opacitymedium">Se han creado tipos de tercero (diccionario): '.dol_escape_htmltag(implode(', ', $createdTypent)).'</div>';
		}
	}

	if (!empty($r['details']) && is_array($r['details'])) {
		print '<br><div class="fichecenter">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre"><th>Detalles</th></tr>';
		foreach ($r['details'] as $d) {
			print '<tr><td>'.dol_escape_htmltag($d).'</td></tr>';
		}
		print '</table>';
		print '</div>';
	}
}

llxFooter();
