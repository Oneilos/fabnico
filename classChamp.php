<?php
class champ {

    private $present;
    private $defaultInsert;
    private $defaultFiltre;
    private $filtre;
    private $primary=false;

    public function __construct($options=null) {

        foreach ((array) $options as $key=>$value) {
            $this->{$key} = $value;
        }

    }

    public function isPresent($lieu)
    {
        if (null===$this->getPresent()) {
            $this->setPresent(array('l', 'f')); // par défaut les champs sont présent en liste & fiche
        }
        return in_array($lieu, $this->getPresent());
    }

    public function getSaveRender($value)
    {
        switch ($this->getClass()) {
            case 'datepicker':
                $tmp = explode('/', $value);
                krsort($tmp);
                $value = implode('-', $tmp);
                return "{$this->getCode()}='{$value}'";
                break;
            default:
                return "{$this->getCode()}='{$value}'";
                break;
        }
    }

    public function getFormRender($value)
    {
        switch ($this->getClass()) {
            case 'libelle':
                return '
                    <label for="' . $this->getCode() . '">' . $this->getLibelle() . '</label>
                    <input type="text" name="' . $this->getCode() . '" id="' . $this->getCode() . '" value="' . $value . '" />
                ';
                break;
            case 'libelle_long':
                return '
                    <label for="' . $this->getCode() . '">' . $this->getLibelle() . '</label>
                    <textarea name="' . $this->getCode() . '" id="' . $this->getCode() . '">' . $value . '</textarea>
                ';
                break;
            case 'datepicker':
            case 'timepicker':
                if ($this->getClass()=='datepicker') {
                    $tmp = explode('-', $value);
                    krsort($tmp);
                    $value = implode('/', $tmp);
                }
                return '
                    <label for="' . $this->getCode() . '">' . $this->getLibelle() . '</label>
                    <input type="text" name="' . $this->getCode() . '" id="' . $this->getCode() . '" class="' . $this->getClass() . '" value="' . $value . '" />
                ';
                break;
            case '1n':
            case 'enum':
                if ($this->getClass()=='1n') {
                    $v = $this->getValues();
                    $result = mysql_query($sql = "SELECT {$v['key']}, concat({$v['libelle']}) FROM {$v['from']}");
                    while (list($key, $libelle) = mysql_fetch_row($result)) {
                        $datas[] = '<option ' . ($value==$key ? ' selected' : '') . ' value="' . $key . '">' . $libelle . '</option>';
                    }
                } else {
                    foreach ($this->getValues() as $key=>$libelle) {
                        $datas[] = '<option ' . ($value==$key ? ' selected' : '') . ' value="' . $key . '">' . $libelle . '</option>';
                    }
                }
                return '
                    <label>' . $this->getLibelle() . '</label>
                    <select name="' . $this->getCode() . '" id="' . $this->getCode() . '"><option>...</option>' . implode('', $datas) . '</select>
                ';
                break;
            case 'nn':
                $v = $this->getValues();
                $result = mysql_query($sql = "SELECT {$v['key']}, {$v['libelle']} FROM {$v['from']}");
                while (list($key, $libelle) = mysql_fetch_row($result)) {
                    $datas[] = '<div style="clear:both;" ><input type="checkbox" class="checkbox_nn" ' . (in_array($key, $value) ? ' checked' : '') . ' name="' . $this->getCode() . '[' . $key . ']" value="1"><span class="span_nn">' . $libelle . '</span></div>';
                }
                return '
                    <label>' . $this->getLibelle() . '</label>
                    <div class="div_nn" >
                    ' . implode('', $datas) . '
                    </div>
                ';
                break;
        }
    }

    public function getSqlFiltreRender($value)
    {
        switch ($this->getFiltre()) {
            case '<=>=':
                if ($value['MIN']) {
                    $tmp = explode('/', $value['MIN']);
                    krsort($tmp);
                    $value['MIN'] = implode('-', $tmp);
                    $data[] = "{$this->getCode()} >= '{$value['MIN']}'";
                }
                if ($value['MAX']) {
                    $tmp = explode('/', $value['MAX']);
                    krsort($tmp);
                    $value['MAX'] = implode('-', $tmp);
                    $data[] = "{$this->getCode()} <= '{$value['MAX']}'";
                }
                if (count($data)) {
                    return implode(' AND ', $data);
                }
                break;
            case "like '%?'":
            case "like '%?%'":
            case "like '?%'":
                if ($value) {
                    return "{$this->getCode()} " . str_replace('?', $value, $this->getFiltre());
                }
                break;
            default:
                if ($value) {
                    return "{$this->getCode()} {$this->getFiltre()} '{$value}'";
                }
                break;
        }
    }

    public function getFiltreRender($value)
    {
        switch ($this->getClass()) {
            case 'libelle_long':
                return '
                    <label for="' . $this->getCode() . '">' . $this->getLibelle() . '</label>
                    <input type="text" name="' . $this->getCode() . '" id="' . $this->getCode() . '" value="' . $value . '" />
                ';
                break;
            case 'datepicker':
                if ($this->getFiltre()=='<=>=') {
                    return '
                        <label for="' . $this->getCode() . '">' . $this->getLibelle() . ' du</label>
                        <input type="text" name="' . $this->getCode() . '[MIN]" id="' . $this->getCode() . '_MIN" class="' . $this->getClass() . '" value="' . $value['MIN'] . '" />
                         <label for="' . $this->getCode() . '">' . $this->getLibelle() . ' au</label>
                        <input type="text" name="' . $this->getCode() . '[MAX]" id="' . $this->getCode() . '_MAX" class="' . $this->getClass() . '" value="' . $value['MAX'] . '" />
                    ';
                    break;
                }// pas de break ici c'est exprès
            default:
                return $this->getFormRender($value);
                break;
        }
    }

    public function getListeRender($value)
    {
        if ($this->isPresent('l')) {
            switch ($this->getClass()) {
                case '1n':
                case 'enum':
                    if ($this->getClass()=='1n') {
                        $v = $this->getValues();
                        $result = mysql_query($sql = "SELECT {$v['key']}, concat({$v['libelle']}) FROM {$v['from']}");
                        while (list($key, $libelle) = mysql_fetch_row($result)) {
                            if ($value==$key) {
                                return '<td' . ($this->getClass() ? ' class="' . $this->getClass() . '"' : '') . '>' . $libelle . '</td>';
                            }
                        }
                        return '<td' . ($this->getClass() ? ' class="' . $this->getClass() . '"' : '') . '></td>';
                    } else {
                        $tab = $this->getValues();
                        return '<td' . ($this->getClass() ? ' class="' . $this->getClass() . '"' : '') . '>' . $tab[$value] . '</td>';
                    }
                    break;
                case 'datepicker':
                    if ($this->getClass()=='datepicker') {
                        $tmp = explode('-', $value);
                        krsort($tmp);
                        $value = implode('/', $tmp);
                    }
                    return '<td' . ($this->getClass() ? ' class="' . $this->getClass() . '"' : '') . '>' . $value . '</td>';
                    break;
                default:
                    return '<td' . ($this->getClass() ? ' class="' . $this->getClass() . '"' : '') . '>' . $value . '</td>';
                    break;
            }
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
