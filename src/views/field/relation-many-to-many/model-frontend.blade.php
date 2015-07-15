<?php 
    
    $jsUnique = str_random();
    $disabled = false;
	$values = [];

    $domAttr = [
		'id' => 'relation-many-to-many-' . $jsUnique,
		'class' => 'chosen',
		'multiple' => 'multiple',
		'data-placeholder' => e($controller->LL('notice.choose')),
	];
	
	if ( (!$model->exists && (!$field->allow_create || !$permissionCreate)) || ($model->exists && (!$field->allow_update || !$permissionUpdate)) )
    {
        $domAttr['disabled'] = 'disabled';
        $disabled = true; 
    }

	if ($v = \App\Telenok\Core\Model\Object\Sequence::where('sequences_object_type', $field->relation_many_to_many_has?:$field->relation_many_to_many_belong_to)->take(20)->get())
	{
		$values = $v->transform(function($item)
		{
			return ['id' => $item->id, 'value' => $item->translate('title')];
		})->lists('value', 'id');
	}
	
	$values = ['&nbsp;'] + $values;
?>

<div class="form-group">

	{!! Form::label("{$field->code}", $field->translate('title'), array('class' => 'col-sm-3 control-label no-padding-right')) !!}

	<div class="col-sm-5">

            @if ($field->icon_class)
		<div class="input-group">
            <span class="input-group-addon">
                <i class="{{ $field->icon_class }}"></i>
            </span>
            @else
		<div>
            @endif	
            
            {!! Form::select($field->code . '[]', $values, $model->exists ? $model->{$field->code}->keys() : [], $domAttr) !!}

			<?php

			$controllerAction->addCssFile(asset('packages/telenok/core/js/jquery.chosen/chosen.css', 'chosen'));
			$controllerAction->addJsFile(asset('packages/telenok/core/js/jquery.chosen/chosen.js', 'chosen'));

			ob_start();

			?>

			<script type="text/javascript">

				jQuery(function()
				{
					jQuery("#relation-many-to-many-{{ $jsUnique }}").ajaxChosen({ 
						keepTypingMsg: "{{ $controller->LL('notice.typing') }}",
						lookingForMsg: "{{ $controller->LL('notice.looking-for') }}",
						type: "GET",
						url: "{!! URL::route("cmf.field.relation-many-to-many.list.title", ['id' => $field->relation_many_to_many_has ?: $field->relation_many_to_many_belong_to]) !!}", 
						dataType: "json",
						minTermLength: 1
					}, 
					function (data) 
					{
						var results = [];

						jQuery.each(data, function (i, val) {
							results.push({ value: val.value, text: val.text });
						});

						return results;
					},
					{
						width: "100%",
						no_results_text: "{{ $controller->LL('notice.not-found') }}"
					});

				});

			</script>

			<?php

			$jsCode = ob_get_contents();

			ob_end_clean();

			$controllerAction->addJsCode($jsCode); 

			?>
			
            @if ($field->translate('description'))
            <span title="" data-content="{{ $field->translate('description') }}" data-placement="right" data-trigger="hover" data-rel="popover" 
                  class="help-button" data-original-title="{{trans('core::default.tooltip.description')}}">?</span>
            @endif
            
		</div>
	</div> 
</div>