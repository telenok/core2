<?php namespace Telenok\Core\Field\System\Permission;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Controller extends \Telenok\Core\Interfaces\Field\Controller {

	protected $key = 'permission';
    protected $allowMultilanguage = false; 
	protected $specialField = ['permission_default'];

	public function getModelFieldViewVariable($controller = null, $model = null, $field = null, $uniqueId = null)
	{
		return
		[
			'urlListTitle' => route("telenok.field.permission.list.title"),
		];
	}

    public function getModelSpecialAttribute($model, $key, $value)
    {
        if (in_array($key, ['permission_default'], true))
        {
            return \Illuminate\Support\Collection::make((array)json_decode($value, true));
        }

        return parent::getModelSpecialAttribute($model, $key, $value);
    }

    public function setModelSpecialAttribute($model, $key, $value)
    {
        if (in_array($key, ['permission_default'], true))
        {
			if ($value instanceof \Illuminate\Support\Collection) 
			{
				$value = $value->toArray();
			}

			$model->setAttribute($key, json_encode((array)$value, JSON_UNESCAPED_UNICODE));
        }
        else
        {
            return parent::setModelSpecialAttribute($model, $key, $value);
        }

        return $this;
    }

	public function preProcess($model, $type, $input)
	{  
		$input->put('title', ['en' => 'Permission']);
		$input->put('title_list', ['en' => 'Permission']);
		$input->put('code', 'permission');
		$input->put('active', 1);
		$input->put('multilanguage', 0);
		$input->put('field_order', $input->get('field_order', 4)); 
		$input->put('allow_search', $input->get('allow_search', 1));

		if (!$input->get('field_object_tab'))
		{
			$input->put('field_object_tab', 'additionally');
		}

		$tab = $this->getFieldTab($input->get('field_object_type'), $input->get('field_object_tab'));

		$input->put('field_object_tab', $tab->getKey());  

		return parent::preProcess($model, $type, $input);
	}

	public function getFormModelContent($controller = null, $model = null, $field = null, $uniqueId = null)
	{
        $permissions = $model->type()->permissionType()->get();

        if (!$permissions->count())
        {
            $permissions = \App\Telenok\Core\Model\Security\Permission::active()->get();
        }

		$this->setViewModel($field, $controller->getModelFieldView($field), $controller->getModelFieldViewKey($field));

		return view($this->getViewModel(), array_merge([
						'controllerParent' => $controller,
						'controller' => $this,
						'model' => $model,
						'field' => $field,
						'uniqueId' => $uniqueId,
						'permissions' => $permissions,
						'permissionCreate' => app('auth')->can('create', 'object_field.' . $model->getTable() . '.' . $field->code),
						'permissionUpdate' => app('auth')->can('update', 'object_field.' . $model->getTable() . '.' . $field->code),
					],
					(array)$this->getModelFieldViewVariable($controller, $model, $field, $uniqueId),
					(array)$controller->getModelFieldViewVariable($this, $model, $field, $uniqueId)
				))->render();
	}

    public function setModelAttribute($model, $key, $value, $field) {}
	public function getModelAttribute($model, $key, $value, $field) {}
	
	public function saveModelField($field, $model, $input)
	{ 
		if (app('auth')->can('update', 'object_field.' . $model->getTable() . '.permission'))
		{
            $permissions = \App\Telenok\Core\Model\Security\Permission::active()->get();

			$permissionList = (array)$input->get('permission', []);

            $permissionListDefault = $field->permission_default;
            
			\App\Telenok\Core\Security\Acl::resource($model)->unsetPermission();

            foreach($permissions->all() as $permission)
            {
                $persmissionIds = [];
                
                if (isset($permissionList[$permission->code]))
                {
                    $persmissionIds = $permissionList[$permission->code];
                }
                else if ($permissionListDefault->get($permission->code))
                {
                    $persmissionIds = $permissionListDefault->get($permission->code);
                }

                foreach($persmissionIds as $id)
                {
                    \App\Telenok\Core\Security\Acl::subject($id)->setPermission($permission->code, $model);
                }
            }
		}

		return $model;
	}

	public function getListFieldContent($field, $item, $type = null)
	{
		$items = [];
		$rows = \Illuminate\Support\Collection::make(\App\Telenok\Core\Model\Security\Permission::take(8)->get());

		if ($rows->count())
		{
			foreach ($rows->slice(0, 7, TRUE) as $row)
			{
				$items[] = $row->translate('title');
			}

			return e('"' . implode('", "', $items) . '"' . (count($rows) > 7 ? ', ...' : ''));
		}
	}
    
    public function getFilterContent($field = null)
    {
        return view($this->getViewFilter(), [
            'controller' => $this,
            'field' => $field,
            'permissions' => \App\Telenok\Core\Model\Security\Permission::active()->get(),
        ])->render();
    }

    public function getFilterQuery($field = null, $model = null, $query = null, $name = null, $value = null) 
    {
		if ($value !== null)
		{
            $sequence = new \App\Telenok\Core\Model\Object\Sequence();
            $spr = new \App\Telenok\Core\Model\Security\SubjectPermissionResource();
            $type = new \App\Telenok\Core\Model\Object\Type();

            foreach((array)$value as $permissionId => $ids)
            {
                $query->join($sequence->getTable() . ' AS sequence_filter_' . $permissionId, function($query) use ($permissionId, $model) 
                {
                    $query->on($model->getTable() . '.id', '=', 'sequence_filter_' . $permissionId . '.id');
                })
                ->join($spr->getTable() . ' AS spr_filter_' . $permissionId, function($query) use ($permissionId) 
                {
                    $query->on('sequence_filter_' . $permissionId . '.id', '=', 'spr_filter_' . $permissionId . '.acl_resource_object_sequence');
                })
                ->join($type->getTable() . ' AS type_filter_' . $permissionId, function($query) use ($permissionId) 
                {
                    $query->on('sequence_filter_' . $permissionId . '.sequences_object_type', '=', 'type_filter_' . $permissionId . '.id');
                })
                ->active('spr_filter_' . $permissionId)
                ->active('type_filter_' . $permissionId)
                ->whereIn('spr_filter_' . $permissionId . '.acl_subject_object_sequence', (array)$ids)
                ->where('spr_filter_' . $permissionId . '.acl_permission_object_sequence', $permissionId);
            }
		}
    }
}