@if($row->field == 'fqdn')
        <?php 
        $systemSite = \App\Tenant::getRootFqdn();
        $dataTypeContent->fqdn = preg_replace('/(.*)(\.' . $systemSite . '$)/', '$1', $dataTypeContent->fqdn);
        ?>
@endif

<input @if($row->required == 1) required @endif type="text" class="form-control" name="{{ $row->field }}"
        placeholder="{{ isset($options->placeholder)? old($row->field, $options->placeholder): $row->display_name }}"
       {!! isBreadSlugAutoGenerator($options) !!}
       value="@if(isset($dataTypeContent->{$row->field})){{ old($row->field, $dataTypeContent->{$row->field}) }}@elseif(isset($options->default)){{ old($row->field, $options->default) }}@else{{ old($row->field) }}@endif">
