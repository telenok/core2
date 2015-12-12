<?php

namespace Telenok\Core\Interfaces\Field\Relation;

class Controller extends \Telenok\Core\Interfaces\Field\Controller {

	protected static $macroFile = 'Model/macro.php';

	public static function readMacroFile()
	{
		$path = app_path(static::$macroFile);
		
		if (!file_exists($path))
		{
			file_put_contents($path, '<?php ' . PHP_EOL . PHP_EOL, LOCK_EX);
		}
		
		require $path;
	}

	public function getLinkedField($field)
	{
	}

	public function getChooseTypeId($field)
	{
		return $field->{$this->getLinkedField($field)};
	}

	public function getModelAttribute($model, $key, $value, $field)
	{
		return $value;
	}

	public function validateExistsInputField($input, $param = [])
	{
		foreach ((array) $param as $p)
		{
			if ($input->get($p))
			{
				return;
			}
		}

		throw new \Exception('Please, define one or more keys "' . implode('", "', (array) $param) 
                . '" for object_field "' . $input->get('code') . '"'
                . ' and object_type "' . $input->get('field_object_type')
                . '"');
	}

	public function getListButton($item, $field = null, $type = null, $uniqueId = null, $canUpdate = null)
	{
        $random = str_random();
        
        $collection = collect();
        
        $collection->put('open', ['order' => 0 , 'content' => 
            '<div class="dropdown">
                <a class="btn btn-white no-hover btn-transparent btn-xs dropdown-toggle" href="#" role="button" style="border:none;"
                        type="button" id="' . $random . '" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true">
                    <span class="glyphicon glyphicon-menu-hamburger text-muted"></span>
                </a>
                <ul class="dropdown-menu" aria-labelledby="' . $random . '">
            ']);
        
        $collection->put('close', ['order' => PHP_INT_MAX, 'content' => 
                '</ul>
            </div>']);
        
        $collection->put('edit', ['order' => 1000, 'content' => 
                '<li><a href="#" onclick="editTableRow' . $field->code . $uniqueId . '(this, \'' 
                    . route($this->getRouteWizardEdit(), ['id' => $item->getKey(), 'saveBtn' => 1, 'chooseBtn' => 0]) . '\'); return false;">' 
                    . ' <i class="fa fa-pencil"></i> ' . $this->LL('list.btn.edit') . '</a>
                </li>']);
        
        $collection->put('delete', ['order' => 2000, 'content' => 
                '<li><a href="#" onclick="deleteTableRow' . $field->code . $uniqueId . '(this); return false;">'
                    . ' <i class="fa fa-trash-o"></i> ' . $this->LL('list.btn.delete') . '</a>
                </li>']);

        app('events')->fire($this->getListButtonEventKey(), $collection);

        return $this->getAdditionalListButton($item, $collection)->sort(function($a, $b)
                    {
                        return array_get($a, 'order', 0) > array_get($b, 'order', 0) ? 1 : -1;
                    })->implode('content');
	}

    public function getListButtonEventKey($param = null)
    {
        return 'telenok.field.' . $this->getKey();
    }

    public function getAdditionalListButton($item, $collection)
    {
        return $collection;
    }

    public function getListFieldContent($field, $item, $type = null)
	{
		$items = [];
		$rows = collect($this->getListFieldContentItems($field, $item, $type));

		if ($rows->count())
		{
			foreach ($rows->slice(0, 7, TRUE) as $row)
			{
				$items[] = \Str::limit($row->translate('title'), 20);
			}

			return e('"' . implode('", "', $items) . '"' . (count($rows) > 7 ? ', ...' : ''));
		}
	}

	public function getListFieldContentItems($field, $item, $type = null)
	{
		return $item->{camel_case($field->code)}()->take(8)->get();
	}

    public function schemeCreateExtraField($table, $p1 = null, $p2 = null, $p3 = null, $p4 = null, $p5 = null)
    {   
    }
}