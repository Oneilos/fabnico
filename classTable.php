<?php
class table {

    private $jointures;
    private $select;
    private $rows;
    private $id;

    public function __construct($options=null)
    {
        foreach ((array) $options as $key=>$value) {
            $this->{$key} = $value;
        }
        $this->setCurrentPage(isset($_REQUEST['page']) ? $_REQUEST['page'] : 1);
    }

    public function getId()
    {
        if (null===$this->id) {
            foreach ($this->getChamps() as $champ) {
                if ($champ->getPrimary()) {
                    $this->id = $champ->getCode();
                }
            }
        }
        return $this->id;
    }

    public function getLibelleTitre()
    {
        return ucFirst(strToLower($this->getLibelle()));
    }

    public function getLibelleUnUne()
    {
        return ($this->getSexe()=='M' ? 'un' : 'une') . ' ' . strToLower($this->getLibelle());
    }

    public function getLibelleLeLa()
    {
        return ($this->getSexe()=='M' ? 'le' : 'la') . ' ' . strToLower($this->getLibelle());
    }

    public function addChamps($champ)
    {
        if (isset($champ->jointure)) {
            $this->addJointures(array_merge(array('code'=>$champ->code), $champ->jointure));
        }
        return $this->__call('addChamps', array($champ));
    }

    public function getJointureSelect()
    {
        $datas = array();
        if (count($this->getJointures())) {
            foreach ($this->getJointures() as $jointure) {
                $datas[] = "LEFT JOIN {$jointure['join']} ON({$jointure['on']})";
            }
        }
        return implode(' ', $datas);
    }

    public function getRows()
    {
        if (null===$this->rows) {
            $result = mysql_query($this->getSelect()) or die($this->getSelect());
            while ($row = mysql_fetch_object($result)) {
                foreach ($row as $key=>$value) {
                    $this->rows[$row->{$this->getId()}]->{$key} = $value;
                }
                if (count($this->getJointures())) {
                    foreach ($this->getJointures() as $jointure) {
                        $this->rows[$row->{$this->getId()}]->{$jointure['code']}[] = $row->{$jointure['select']};
                    }
                }
            }
        }
        return $this->rows;
    }

    public function getRow()
    {
        $rows = $this->getRows();
        return $rows[key($rows)];
    }

    public function getNbRows()
    {
        if (null===$this->nbrows) {
            $this->nbrows = count($this->getRows());
        }
        return $this->nbrows;
    }

    public function save()
    {
        if (!is_array($_REQUEST[$this->getId()]) && null===$this->getSelect()) {
            $this->setSelect("SELECT * FROM {$this->getName()} {$this->getJointureSelect()} WHERE {$this->getId()}='{$_REQUEST[$this->getId()]}'");
        }

        if (is_array($_REQUEST[$this->getId()])) {
            $present = 'fm';
        } else {
            $present = 'f';
        }

        foreach ($this->getChamps() as $champ) {
            if (isset($_REQUEST[$champ->getCode()]) && ($champ->isPresent($present)) && $champ->getClass()!='nn') {
                $_REQUEST[$champ->getCode()] = addSlashes($_REQUEST[$champ->getCode()]);
                $data[] = $champ->getSaveRender($_REQUEST[$champ->getCode()]);
            } elseif ($champ->getDefaultInsert() && (!isset($_REQUEST[$this->getId()]) || empty($this->getRow()->{$champ->getCode()})) && $champ->getClass()!='nn') {
                $data[] = $champ->getSaveRender($champ->getDefaultInsert());
            }
        }


        if (empty($_REQUEST[$this->getId()])) {
            mysql_query("INSERT INTO {$this->getName()} SET " . implode(', ', $data));
            $ids[] = mysql_insert_id();
        } else {
            foreach ((array) $_REQUEST[$this->getId()] as $id) {
                mysql_query("UPDATE {$this->getName()} SET " . implode(', ', $data) . " WHERE {$this->getId()}='{$id}'");
                $ids[] = $_REQUEST[$this->getId()];
            }
        }
        if (count($this->getJointures())) {
            foreach ($ids as $id) {
                foreach ($this->getJointures() as $jointure) {
                    mysql_query("DELETE FROM {$jointure['join']} WHERE {$jointure['where']}='{$id}'");
                    foreach (array_keys($_REQUEST[$jointure['code']]) as $value) {
                        mysql_query("INSERT INTO {$jointure['join']} SET {$jointure['where']}='{$id}', {$jointure['select']}='{$value}'");
                    }
                }
            }
        }
        return 'OK';
    }
    public function delete()
    {
        if (!empty($_REQUEST[$this->getId()])) {
            mysql_query("DELETE FROM {$this->getName()} WHERE {$this->getId()}='{$_REQUEST[$this->getId()]}'");
        }
        if (count($this->getJointures())) {
            foreach ($this->getJointures() as $jointure) {
                mysql_query("DELETE FROM {$jointure['join']} WHERE {$jointure['where']}='{$_REQUEST[$this->getId()]}'");
            }
        }
        return 'OK';
    }

    public function getForm()
    {
        if (!is_array($_REQUEST[$this->getId()]) && null===$this->getSelect()) {
            $this->setSelect("SELECT * FROM {$this->getName()} {$this->getJointureSelect()} WHERE {$this->getId()}='{$_REQUEST[$this->getId()]}'");
        }
    	$aff = '
        <form method="post" action="' . $this->getUrl() . '" class="fbn_form">
            <fieldset id="fieldset_infos">
                <legend>' . (is_array($_REQUEST[$this->getId()]) ? 'Modification de masse des ' . $this->getLibelle() . 's' : 'Informations sur ' . $this->getLibelleLeLa()) . '</legend>
        ';

        if (is_array($_REQUEST[$this->getId()])) {
            $present = 'fm';
        } else {
            $present = 'f';
        }
        foreach ($this->getChamps() as $champ) {
            if ($champ->isPresent($present)) {
                $aff .= $champ->getFormRender((!isset($_REQUEST[$this->getId()]) || is_array($_REQUEST[$this->getId()])) ? '' : $this->getRow()->{$champ->getCode()});
            }
        }

        $aff .= '
                <div style="clear:both;"></div>

            </fieldset>

            <input type="hidden" name="action" value="save" />
            <input type="hidden" name="t" value="' . $this->getCode() .'" />
        ';

        if (isset($_REQUEST[$this->getId()])) {
            if (is_array($_REQUEST[$this->getId()])) {
                foreach ($_REQUEST[$this->getId()] as $id) {
                    $aff .= '<input type="hidden" name="' . $this->getId() . '[]" value="' . $id . '" />';
                }
            } else {
                $aff .= '<input type="hidden" name="' . $this->getId() . '" value="' . $this->getRow()->{$this->getId()} . '" />';
            }
        }

        $aff .= '
            <input type="submit" value="Enregistrer" class="submit" />

        </form>
        ';
        return $aff;
    }

    public function getSqlFiltre()
    {
        foreach ($this->getChamps() as $champ) {
            if ($champ->getFiltre()) {
                if (isset($_REQUEST[$champ->getCode()])) {
                    if ($sqlFiltre = $champ->getSqlFiltreRender($_REQUEST[$champ->getCode()])) {
                        $data[] = $sqlFiltre;
                    } 
                } elseif ($champ->getDefaultFiltre()) {
                    if ($sqlFiltre = $champ->getSqlFiltreRender($champ->getDefaultFiltre())) {
                        $data[] = $sqlFiltre;
                    } 
                }
            }
        }
        if (count($data)) {
            return ' WHERE ' .  implode(' AND ', $data) . ' ';
        }
    }

    public function getFormFiltre()
    {
        $nbFiltre=0;
        $filtres = '
                <fieldset id="fieldset_infos">
                    <legend>Filtres</legend>
                    <div>
        ';
        foreach ($this->getChamps() as $champ) {
            if ($champ->getFiltre()) {
                $filtres .= $champ->getFiltreRender(isset($_REQUEST[$champ->getCode()]) ? $_REQUEST[$champ->getCode()] : $champ->getDefaultFiltre());
                $nbFiltre++;
            }
        }
        $filtres .= '
                    </div>
                    <input type="submit" value="Rechercher" class="submit" />
                    <input type="reset" value="reset" class="submit" />
                    <a href="' . $this->getUrl() . '?t=' . $this->getCode() .'">Annuler</a>
                </fieldset>
        ';

        if ($this->getNbRows()) {
            if ($this->getNbRows()>1) {$s = 's';} else {$s = '';}
            $paginateur = '<div>';
            $paginateur .= '<div><span class="fbn_nb_result">' . $this->getNbRows() . ' enregistrement' . ($s) . '</span></div>';
            if ($this->getNbParPage() && ($nbPage = ceil($this->getNbRows()/$this->getNbParPage()))>1) {
                $paginateur .= '<span class="fbn_paginateur">';
                for ($i=1 ; $i<=$nbPage ; $i++) {
                    $paginateur .= '<input type="submit" name="page" value="' . $i . '"' . ($this->getCurrentPage()==$i ? ' class="active"' : '') . ' />';
                }
                $paginateur .= '</span>';
            }
            $paginateur .= '</div>';
        }

        $aff = '<form method="get" action="' . $this->getUrl() . '" class="fbn_search">';
        if ($nbFiltre) {
            $aff .= $filtres;
        }
        if ($this->getNbRows()) {
            $aff .= $paginateur;
        }
        $aff .= '<input type="hidden" name="t" value="' . $this->getCode() .'" />';
        $aff .= '</form>';

        if ($nbFiltre || $this->getNbRows()) {
            return $aff;
        }
    }

    public function getListe()
    {
        if (null===$this->getSelect()) {
            $this->setSelect("SELECT * FROM {$this->getName()} {$this->getJointureSelect()} {$this->getSqlFiltre()}");
        }
        $aff = '
            <h2>' . $this->getLibelleTitre() . '</h2>
            <a href="' . $this->getUrl() . '?t=' . $this->getCode() . '&action=form" title="Ajouter ' . $this->getLibelleUnUne() . '" class="fbn_add">Ajouter ' . $this->getLibelleUnUne() . '</a>
        ';

        $aff .= $this->getFormFiltre();

        $aff .= '
            <table style="width:100%;">
                <thead>
                    <tr>
        ';
        $nbCols = 0;
        foreach ($this->getChamps() as $champ) {
            if ($champ->isPresent('l')) {
                $nbCols++;
                $aff .= '<th>' . $champ->getLibelle() . '</th>';
            }
        }
        $aff .= '
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
        ';
        if ($this->getNbRows()) {
            foreach ($this->getRows() as $row) {
                if ($this->getNbParPage() && $this->getCurrentPage()>1 && $i++<(($this->getCurrentPage()-1)*$this->getNbParPage()) ) {
                    continue;
                }
                if ($this->getNbParPage() && $i++>=($this->getCurrentPage()*$this->getNbParPage()) ) {
                    break;
                }
                $aff .= '
                    <tr>
                ';
                foreach ($this->getChamps() as $champ) {
                    if ($champ->isPresent('l')) {
                        $aff .= $champ->getListeRender($row->{$champ->getCode()});
                    }
                }
                $aff .= '
                        <td>
                            <a href="' . $this->getUrl() . '?t=' . $this->getCode() . '&' . $this->getId() . '=' . $row->{$this->getId()} . '&action=form" class="fbn_edit" title="Consulter / Modifier ' . $this->getLibelleLeLa() . '">M</a>
                            <a href="' . $this->getUrl() . '?t=' . $this->getCode() . '&' . $this->getId() . '=' . $row->{$this->getId()} . '&action=delete" class="fbn_delete" title="Supprimer ' . $this->getLibelleLeLa() . '">S</a>
                        </td>
                    </tr>
                ';
            }
        } else {
            $aff .= '
                <tr>
                    <td colspan="' . ($nbCols+1) . '" class="fbn_noresult">Aucun résultat ne correspond à votre recherche</td>
                </tr>
            ';
        }
        $aff .= '
                </tbody>
            </table>
        ';
        return $aff;
    }

    private function getUrl()
    {
        return substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], '?'));
    }

    public function __toString()
    {
        switch ($_REQUEST['action']) {
            case 'save' :
                return $this->save();
                break;
            case 'form' :
                return $this->getForm();
                break;
            case 'delete' :
                return $this->delete();
                break;
            default :
                return $this->getListe();
                break;
        }
    }

    public function __call($method, $args)
    {
        $method = strTolower($method);
        if (substr($method, 0, 3)=='set') {
            $this->{substr($method, 3)} = $args[0];
            return $this;
        }
        if (substr($method, 0, 3)=='get') {
            return $this->{substr($method, 3)};
        }
        if (substr($method, 0, 3)=='add') {
            $this->{substr($method, 3)}[] = $args[0];
            return $this;
        }
    }

}