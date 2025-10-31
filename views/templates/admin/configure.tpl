{extends file="helpers/view/view.tpl"}
{block name="view"}
<div class="panel">
    <h3><i class="icon-cloud-download"></i> OpenCart 3 → PrestaShop Importer</h3>
    {include file="modules/ocimporterps/views/templates/admin/partials/connection_form.tpl"}
    {include file="modules/ocimporterps/views/templates/admin/partials/run_controls.tpl"}
    {include file="modules/ocimporterps/views/templates/admin/partials/progress.tpl"}
</div>
{/block}
