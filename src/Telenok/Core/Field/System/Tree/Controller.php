<?php namespace Telenok\Core\Field\System\Tree;

/**
 * @class Telenok.Core.Field.System.Tree.Controller
 * Class of field "tree". Field allow to store data in tree.
 *  
 * @extends Telenok.Core.Field.RelationManyToMany.Controller
 */
class Controller extends \Telenok\Core\Field\RelationManyToMany\Controller {

    /**
     * @protected
     * @property {String} $key
     * Field key.
     * @member Telenok.Core.Field.System.Tree.Controller
     */
    protected $key = 'tree';
    
    /**
     * @protected
     * @property {String} $viewModel
     * View to show field form-element when creating or updating {Telenok.Core.Interfaces.Eloquent.Object.Model}
     * @member Telenok.Core.Field.System.Tree.Controller
     */
    protected $viewModel = "core::field.relation-many-to-many.model";

    /**
     * @protected
     * @property {String} $viewField
     * View to show special field's form-element when creating or updating {Telenok.Core.Model.Object.Field}
     * @member Telenok.Core.Field.System.Tree.Controller
     */
    protected $viewField = "core::field.tree.field";

    /**
     * @method getChooseTypeId
     * Return ID of linked Type Object.
     * 
     * @param {Telenok.Core.Model.Object.Field} $field
     * Object with data of field's configuration.
     * @return {Integer}
     * @member Telenok.Core.Field.System.Tree.Controller
     */
    public function getChooseTypeId($field)
    {
        return \App\Telenok\Core\Model\Object\Type::withPermission()->where('treeable', 1)->get(['id'])->fetch('id')->all();
    }

    /**
     * @method getLinkedModelType
     * Return Object Type of field
     * 
     * @param {Telenok.Core.Model.Object.Field} $field
     * @return {Telenok.Core.Model.Object.Type}
     * @member Telenok.Core.Field.System.Tree.Controller
     */
    public function getLinkedModelType($field)
    {
        return \App\Telenok\Core\Model\Object\Type::where('code', 'object_sequence')->first();
    }

    /**
     * @method getFilterQuery
     * Add restrictions to search query.
     * 
     * @param {Telenok.Core.Model.Object.Field} $field
     * Object with data of field's configuration.
     * @param {Object} $model
     * Eloquent object.
     * @param {Illuminate.Database.Query.Builder} $query
     * Laravel query builder object.
     * @param {String} $name
     * Name of field to search for.
     * @param {String} $value
     * Value to search for.
     * @return {void}
     * @member Telenok.Core.Field.System.Tree.Controller
     */
    public function getFilterQuery($field = null, $model = null, $query = null, $name = null, $value = null)
    {
        if (!empty($value))
        {
            $modelTable = $model->getTable();
            $pivotTable = 'pivot_relation_m2m_tree';

            if ($field->relation_many_to_many_has)
            {
                $fieldRelated = 'tree_id';
                $fieldSearchIn = 'tree_pid';
            }
            else
            {
                $fieldRelated = 'tree_pid';
                $fieldSearchIn = 'tree_id';
            }

            $query->join($pivotTable, function($join) use ($pivotTable, $modelTable, $fieldRelated)
            {
                $join->on($pivotTable . '.' . $fieldRelated, '=', $modelTable . '.id');
            });

            $query->whereIn($pivotTable . '.' . $fieldSearchIn, (array) $value);
        }
    }

    /**
     * @method saveModelField
     * Save eloquent model with field's data.
     * 
     * @param {Telenok.Core.Model.Object.Field} $field
     * Eloquent object Field.
     * @param {Telenok.Core.Interfaces.Eloquent.Object.Model} $model
     * Eloquent object.
     * @param {Illuminate.Support.Collection} $input
     * Values of request.
     * @return {Telenok.Core.Interfaces.Eloquent.Object.Model}
     * @member Telenok.Core.Field.System.Tree.Controller
     */
    public function saveModelField($field, $model, $input)
    {
        if (!$model->sequence->treeable)
        {
            throw new \Exception('Model "' . get_class($model) . '" is not treeable');
        }

        if (app('auth')->can('update', 'object_field.' . $model->getTable() . '.tree_parent'))
        {
            $idsParentAdd = array_unique((array) $input->get("tree_parent_add", []));
            $idsParentDelete = array_unique((array) $input->get("tree_parent_delete", []));

            $idsChildAdd = array_unique((array) $input->get("tree_child_add", []));
            $idsChilDelete = array_unique((array) $input->get("tree_child_delete", []));

            if (!empty($idsParentDelete))
            {
                if (in_array('*', $idsParentDelete, true))
                {
                    $model->treeParent()->detach();
                }
                else if (!empty($idsParentDelete))
                {
                    $model->treeParent()->detach($idsParentDelete);
                }
            }

            if (!empty($idsParentAdd))
            {
                foreach ($idsParentAdd as $id)
                {
                    $model->makeLastChildOf($id);
                }
            }
        }

        if (app('auth')->can('update', 'object_field.' . $model->getTable() . '.tree_child'))
        {
            if (!empty($idsChilDelete))
            {
                if (in_array('*', $idsChilDelete, true))
                {
                    $model->treeChild()->detach();
                }
                else if (!empty($idsChilDelete))
                {
                    $model->treeChild()->detach($idsChilDelete);
                }
            }

            if (!empty($idsChildAdd))
            {
                foreach ($idsChildAdd as $id)
                {
                    try
                    {
                        $child = \App\Telenok\Core\Model\Object\Sequence::findOrFail($id);

                        $child->makeLastChildOf($model);
                    }
                    catch (\Exception $e)
                    {
                        
                    }
                }
            }
        }

        return $model;
    }

    /**
     * @method preProcess
     * Preprocess save {@link Telenok.Core.Model.Object.Field $model}.
     * 
     * @param {Telenok.Core.Model.Object.Field} $model
     * Object to save.
     * @param {Telenok.Core.Model.Object.Type} $type
     * Object with data of field's configuration.
     * @param {Illuminate.Http.Request} $input
     * Laravel request object.
     * @return {Telenok.Core.Field.System.Tree.Controller}
     * @member Telenok.Core.Field.System.Tree.Controller
     */
    public function preProcess($model, $type, $input)
    {
        $sequenceTypeId = app('db')->table('object_type')->where('code', 'object_sequence')->pluck('id');

        $translationSeed = $this->translationSeed();

        if (!$input->get('title'))
        {
            $input->put('title', array_get($translationSeed, 'model.parent'));
        }

        if (!$input->get('title_list'))
        {
            $input->put('title_list', array_get($translationSeed, 'model.parent'));
        }

        $input->put('key', 'tree');
        $input->put('code', 'tree_parent');
        $input->put('relation_many_to_many_has', $sequenceTypeId);
        $input->put('active', 1);
        $input->put('multilanguage', 0);

        if (!$input->get('field_object_tab'))
        {
            $input->put('field_object_tab', 'additionally');
        }

        $tab = $this->getFieldTab($input->get('field_object_type'), $input->get('field_object_tab', 'additionally'));

        $input->put('field_object_tab', $tab->getKey());

        $toSave = [
            'title' => array_get($translationSeed, 'model.children'),
            'title_list' => array_get($translationSeed, 'model.children'),
            'key' => 'tree',
            'code' => 'tree_child',
            'field_object_type' => $input->get('field_object_type'),
            'field_object_tab' => $input->get('field_object_tab'),
            'relation_many_to_many_belong_to' => $sequenceTypeId,
            'show_in_list' => $input->get('show_in_list'),
            'show_in_form' => $input->get('show_in_form'),
            'allow_search' => $input->get('allow_search'),
            'multilanguage' => 0,
            'active' => $input->get('active'),
            'active_at_start' => $input->get('start_at_belong', $model->active_at_start),
            'active_at_end' => $input->get('end_at_belong', $model->active_at_end),
            'allow_create' => $input->get('allow_create'),
            'allow_update' => $input->get('allow_update'),
            'field_order' => $input->get('field_order'),
        ];

        $validator = $this->validator(app('\App\Telenok\Core\Model\Object\Field'), $toSave, []);

        $fieldObjectType = \App\Telenok\Core\Model\Object\Type::find($input->get('field_object_type'));
        $fieldObjectType->treeable = 1;
        $fieldObjectType->save();

        if ($input->get('create_belong') !== false && $validator->passes())
        {
            \App\Telenok\Core\Model\Object\Field::create($toSave);
        }

        return $this;
    }

    /**
     * @method postProcess
     * postProcess save {@link Telenok.Core.Model.Object.Field $model}.
     * 
     * @param {Telenok.Core.Model.Object.Field} $model
     * Object to save.
     * @param {Telenok.Core.Model.Object.Type} $type
     * Object with data of field's configuration.
     * @param {Illuminate.Http.Request} $input
     * Laravel request object.
     * @return {Telenok.Core.Field.System.Tree.Controller}
     * @member Telenok.Core.Field.System.Tree.Controller
     */
    public function postProcess($model, $type, $input)
    {
        return $this;
    }

    /**
     * @method translationSeed
     * Return multilanguage array
     * 
     * @return {Array}
     * @member Telenok.Core.Field.System.Tree.Controller
     */
    public function translationSeed()
    {
        return [
            'model' => [
                'parent' => ['en' => 'Parent', 'ru' => 'Родитель'],
                'children' => ['en' => 'Children', 'ru' => 'Потомок'],
            ],
        ];
    }
}