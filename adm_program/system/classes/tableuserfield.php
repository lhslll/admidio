<?php
declare(strict_types=1);
/**
 ***********************************************************************************************
 * Class manages access to database table adm_user_fields
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class TableUserField
 *
 * Diese Klasse dient dazu einen Benutzerdefiniertes Feldobjekt zu erstellen.
 * Eine Benutzerdefiniertes Feldobjekt kann ueber diese Klasse in der Datenbank
 * verwaltet werden
 *
 * Es stehen die Methoden der Elternklasse TableAccess zur Verfuegung
 */
class TableUserField extends TableAccess
{
    /**
     * @var bool|null Flag if the current user could view this user
     */
    protected $mViewUserField;
    /**
     * @var int|null Flag with the user id of which user the view property was saved
     */
    protected $mViewUserFieldUserId;

    /**
     * Constructor that will create an object of a recordset of the table adm_user_fields.
     * If the id is set than the specific user field will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object @b $gDb.
     * @param int      $usfId    The recordset of the user field with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(Database $database, int $usfId = 0)
    {
        // read also data of assigned category
        $this->connectAdditionalTable(TBL_CATEGORIES, 'cat_id', 'usf_cat_id');

        parent::__construct($database, TBL_USER_FIELDS, 'usf', $usfId);
    }

    /**
     * Additional to the parent method visible roles array and flag will be initialized.
     */
    public function clear()
    {
        parent::clear();

        $this->mViewUserField = null;
        $this->mViewUserFieldUserId = null;
    }

    /**
     * Deletes the selected field and all references in other tables.
     * Also the gap in sequence will be closed. After that the class will be initialize.
     * @return true true if no error occurred
     */
    public function delete(): bool
    {
        global $gCurrentSession;

        $this->db->startTransaction();

        // close gap in sequence
        $sql = 'UPDATE '.TBL_USER_FIELDS.'
                   SET usf_sequence = usf_sequence - 1
                 WHERE usf_cat_id   = ? -- $this->getValue(\'usf_cat_id\')
                   AND usf_sequence > ? -- $this->getValue(\'usf_sequence\')';
        $this->db->queryPrepared($sql, array($this->getValue('usf_cat_id'), $this->getValue('usf_sequence')));

        // close gap in sequence of saved lists
        $sql = 'SELECT lsc_lst_id, lsc_number
                  FROM '.TBL_LIST_COLUMNS.'
                 WHERE lsc_usf_id = ? -- $this->getValue(\'usf_id\')';
        $listsStatement = $this->db->queryPrepared($sql, array($this->getValue('usf_id')));

        while($rowLst = $listsStatement->fetch())
        {
            $sql = 'UPDATE '.TBL_LIST_COLUMNS.'
                       SET lsc_number = lsc_number - 1
                     WHERE lsc_lst_id = ? -- $rowLst[\'lsc_lst_id\']
                       AND lsc_number > ? -- $rowLst[\'lsc_number\']';
            $this->db->queryPrepared($sql, array($rowLst['lsc_lst_id'], $rowLst['lsc_number']));
        }

        // delete all dependencies in other tables
        $sql = 'DELETE FROM '.TBL_USER_LOG.'
                 WHERE usl_usf_id = ? -- $this->getValue(\'usf_id\')';
        $this->db->queryPrepared($sql, array($this->getValue('usf_id')));

        $sql = 'DELETE FROM '.TBL_USER_DATA.'
                 WHERE usd_usf_id = ? -- $this->getValue(\'usf_id\')';
        $this->db->queryPrepared($sql, array($this->getValue('usf_id')));

        $sql = 'DELETE FROM '.TBL_LIST_COLUMNS.'
                 WHERE lsc_usf_id = ? -- $this->getValue(\'usf_id\')';
        $this->db->queryPrepared($sql, array($this->getValue('usf_id')));

        // all active users must renew their user data because the user field structure has been changed
        $gCurrentSession->renewUserObject();

        $return = parent::delete();

        $this->db->endTransaction();

        return $return;
    }

    /**
     * diese rekursive Methode ermittelt fuer den uebergebenen Namen einen eindeutigen Namen
     * dieser bildet sich aus dem Namen in Grossbuchstaben und der naechsten freien Nummer (index)
     * Beispiel: 'Mitgliedsnummer' => 'MITGLIEDSNUMMER_2'
     * @param string $name
     * @param int    $index
     * @return string
     */
    private function getNewNameIntern(string $name, int $index): string
    {
        $newNameIntern = strtoupper(str_replace(' ', '_', $name));

        if ($index > 1)
        {
            $newNameIntern = $newNameIntern . '_' . $index;
        }

        $sql = 'SELECT usf_id
                  FROM '.TBL_USER_FIELDS.'
                 WHERE usf_name_intern = ? -- $newNameIntern';
        $userFieldsStatement = $this->db->queryPrepared($sql, array($newNameIntern));

        if ($userFieldsStatement->rowCount() > 0)
        {
            ++$index;
            $newNameIntern = $this->getNewNameIntern($name, $index);
        }

        return $newNameIntern;
    }

    /**
     * Get the value of a column of the database table.
     * If the value was manipulated before with @b setValue than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read
     * @param string $format     For column @c usf_value_list the following format is accepted: @n
     *                           @b database returns database value of usf_value_list; @n
     *                           @b text extract only text from usf_value_list, image infos will be ignored @n
     *                           For date or timestamp columns the format should be the date/time format e.g. @b d.m.Y = '02.04.2011' @n
     *                           For text columns the format can be @b database that would be the database value without any transformations
     * @return mixed Returns the value of the database column.
     *               If the value was manipulated before with @b setValue than the manipulated value is returned.
     */
    public function getValue(string $columnName, string $format = '')
    {
        global $gL10n;

        if ($columnName === 'usf_description')
        {
            if (!isset($this->dbColumns['usf_description']))
            {
                $value = '';
            }
            elseif ($format === 'database')
            {
                $value = html_entity_decode(strStripTags($this->dbColumns['usf_description']), ENT_QUOTES, 'UTF-8');
            }
            else
            {
                $value = $this->dbColumns['usf_description'];
            }
        }
        elseif ($columnName === 'usf_name_intern')
        {
            // internal name should be read with no conversion
            $value = parent::getValue($columnName, 'database');
        }
        else
        {
            $value = parent::getValue($columnName, $format);
        }

        if ($format !== 'database')
        {
            switch ($columnName)
            {
                case 'usf_name':
                case 'cat_name':
                    // if text is a translation-id then translate it
                    if (admIsTranslationStrId($value))
                    {
                        $value = $gL10n->get(admStrToUpper($value));
                    }

                    break;
                case 'usf_value_list':
                    if ($this->dbColumns['usf_type'] === 'DROPDOWN' || $this->dbColumns['usf_type'] === 'RADIO_BUTTON')
                    {
                        $arrListValuesWithKeys = array(); // array with list values and keys that represents the internal value

                        // first replace windows new line with unix new line and then create an array
                        $valueFormated = str_replace("\r\n", "\n", $value);
                        $arrListValues = explode("\n", $valueFormated);

                        foreach ($arrListValues as $key => &$listValue)
                        {
                            if ($this->dbColumns['usf_type'] === 'RADIO_BUTTON')
                            {
                                // if value is imagefile or imageurl then show image
                                if (admStrEndsWith(admStrToLower($listValue), '.png') || admStrEndsWith(admStrToLower($listValue), '.jpg'))
                                {
                                    // if there is imagefile and text separated by | then explode them
                                    $listValues = explode('|', $listValue);
                                    if (count($listValues) === 1)
                                    {
                                        $listValueImage = $listValue;
                                        $listValueText  = $this->getValue('usf_name');
                                    }
                                    else
                                    {
                                        $listValueImage = $listValues[0];
                                        $listValueText  = $listValues[1];
                                    }

                                    // if text is a translation-id then translate it
                                    if (admIsTranslationStrId($listValueText))
                                    {
                                        $listValueText = $gL10n->get(admStrToUpper($listValueText));
                                    }

                                    if ($format === 'text')
                                    {
                                        // if no image is wanted then return the text part or only the position of the entry
                                        if (count($listValues) === 1)
                                        {
                                            $listValue = $key + 1;
                                        }
                                        else
                                        {
                                            $listValue = $listValueText;
                                        }
                                    }
                                    else
                                    {
                                        try
                                        {
                                            if (!admStrStartsWith(admStrToLower($listValueImage), 'http') || !strValidCharacters($listValueImage, 'url'))
                                            {
                                                if (admStrIsValidFileName($listValueImage, true))
                                                {
                                                    $listValueImage = THEME_URL . '/icons/' . $listValueImage;
                                                }
                                            }

                                            $listValue = '<img class="admidio-icon-info" src="'.$listValueImage.'" title="'.$listValueText.'" alt="'.$listValueText.'" />';
                                        }
                                        catch (AdmException $e)
                                        {
                                            $e->showText();
                                            // => EXIT
                                        }
                                    }
                                }
                            }

                            // if text is a translation-id then translate it
                            if (admIsTranslationStrId($listValue))
                            {
                                $listValue = $gL10n->get(admStrToUpper($listValue));
                            }

                            // save values in new array that starts with key = 1
                            $arrListValuesWithKeys[++$key] = $listValue;
                        }
                        unset($listValue);
                        $value = $arrListValuesWithKeys;
                    }

                    break;
                case 'usf_icon':
                    // if value is imagefile or imageurl then show image
                    if(admStrEndsWith(admStrToLower($value), '.png') || admStrEndsWith(admStrToLower($value), '.jpg'))
                    {
                        try
                        {
                            if (!admStrStartsWith(admStrToLower($value), 'http') || !strValidCharacters($value, 'url'))
                            {
                                if (admStrIsValidFileName($value, true))
                                {
                                    $value = THEME_URL . '/icons/' . $value;
                                }
                            }

                            $value = '<img src="'.$value.'" style="vertical-align: middle;" title="'.$this->getValue('usf_name').'" alt="'.$this->getValue('usf_name').'" />';
                        }
                        catch (AdmException $e)
                        {
                            $e->showText();
                            // => EXIT
                        }
                    }

                    break;
                default:
                    // do nothing
            }
        }

        if ($value === null)
        {
            return '';
        }
        return $value;
    }

    /**
     * das Feld wird um eine Position in der Reihenfolge verschoben
     * @param string $mode
     */
    public function moveSequence(string $mode)
    {
        $mode = admStrToUpper($mode);
        $usfSequence = (int) $this->getValue('usf_sequence');
        $usfCatId    = (int) $this->getValue('usf_cat_id');
        $sql = 'UPDATE '.TBL_USER_FIELDS.'
                   SET usf_sequence = ? -- $usfSequence
                 WHERE usf_cat_id   = ? -- $usfCatId
                   AND usf_sequence = ? -- $usfSequence -/+ 1';

        // die Kategorie wird um eine Nummer gesenkt und wird somit in der Liste weiter nach oben geschoben
        if ($mode === 'UP')
        {
            $this->db->queryPrepared($sql, array($usfSequence, $usfCatId, $usfSequence - 1));
            --$usfSequence;
        }
        // die Kategorie wird um eine Nummer erhoeht und wird somit in der Liste weiter nach unten geschoben
        elseif ($mode === 'DOWN')
        {
            $this->db->queryPrepared($sql, array($usfSequence, $usfCatId, $usfSequence + 1));
            ++$usfSequence;
        }

        $this->setValue('usf_sequence', $usfSequence);
        $this->save();
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update
     * the changed columns. If the table has columns for creator or editor than these column
     * with their timestamp will be updated.
     * For new records the name intern will be set per default.
     * @param bool $updateFingerPrint Default @b true. Will update the creator or editor of the recordset if table has columns like @b usr_id_create or @b usr_id_changed
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     */
    public function save(bool $updateFingerPrint = true): bool
    {
        global $gCurrentSession;

        $fieldsChanged = $this->columnsValueChanged;

        // if new field than generate new name intern, otherwise no change will be made
        if ($this->newRecord)
        {
            $this->setValue('usf_name_intern', $this->getNewNameIntern($this->getValue('usf_name', 'database'), 1));
        }

        $returnValue = parent::save($updateFingerPrint);

        if ($fieldsChanged && $gCurrentSession instanceof Session)
        {
            // all active users must renew their user data because the user field structure has been changed
            $gCurrentSession->renewUserObject();
        }

        return $returnValue;
    }

    /**
     * Set a new value for a column of the database table.
     * The value is only saved in the object. You must call the method @b save to store the new value to the database
     * @param string $columnName The name of the database column whose value should get a new value
     * @param mixed  $newValue The new value that should be stored in the database field
     * @param bool   $checkValue The value will be checked if it's valid. If set to @b false than the value will not be checked.
     * @return bool Returns @b true if the value is stored in the current object and @b false if a check failed
     */
    public function setValue(string $columnName, $newValue, bool $checkValue = true): bool
    {
        if ($columnName === 'usf_description')
        {
            return parent::setValue($columnName, $newValue, false);
        }

        // name, category and type couldn't be edited if it's a system field
        if (in_array($columnName, array('usf_cat_id', 'usf_type', 'usf_name'), true) && (int) $this->getValue('usf_system') === 1)
        {
            return false;
        }

        if ($columnName === 'usf_cat_id' && $this->getValue($columnName) !== $newValue)
        {
            // erst einmal die hoechste Reihenfolgennummer der Kategorie ermitteln
            $sql = 'SELECT COUNT(*) AS count
                      FROM '.TBL_USER_FIELDS.'
                     WHERE usf_cat_id = ? -- $newValue';
            $pdoStatement = $this->db->queryPrepared($sql, array($newValue));

            $this->setValue('usf_sequence', $pdoStatement->fetchColumn() + 1);
        }
        elseif ($columnName === 'usf_url' && $newValue !== '')
        {
            $newValue = admFuncCheckUrl($newValue);

            if ($newValue === false)
            {
                return false;
            }
        }

        return parent::setValue($columnName, $newValue, $checkValue);
    }

    /**
     * This method checks if the current user is allowed to view this profile field. Therefore
     * the visibility of the category is checked. This method will not check the context if
     * the user is allowed to view the field because he has the right to edit the profile.
     * @return bool Return true if the current user is allowed to view this profile field
     */
    public function visible(): bool
    {
        global $gCurrentUser;

        $usrId = (int) $gCurrentUser->getValue('usr_id');

        if ($this->mViewUserField === null || $this->mViewUserFieldUserId !== $usrId)
        {
            $this->mViewUserFieldUserId = $usrId;

            // check if the current user could view the category of the profile field
            $this->mViewUserField = in_array((int) $this->getValue('cat_id'), $gCurrentUser->getAllVisibleCategories('USF'), true);
        }

        return $this->mViewUserField;
    }
}
