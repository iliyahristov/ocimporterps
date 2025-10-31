<form method="post" action="{$form_action|escape}">
    <div class="row">
        <div class="col-lg-6">
            <div class="form-group">
                <label>DB Host</label>
                <input class="form-control" name="ocimp_host" value="{$config.host|escape}">
            </div>
            <div class="form-group">
                <label>DB Name</label>
                <input class="form-control" name="ocimp_db" value="{$config.db|escape}">
            </div>
            <div class="form-group">
                <label>DB User</label>
                <input class="form-control" name="ocimp_user" value="{$config.user|escape}">
            </div>
            <div class="form-group">
                <label>DB Pass</label>
                <input class="form-control" name="ocimp_pass" value="{$config.pass|escape}" type="password">
            </div>
            <div class="form-group">
                <label>OC Table Prefix</label>
                <input class="form-control" name="ocimp_pref" value="{$config.pref|escape}">
            </div>
            <div class="form-group">
                <label>Batch size</label>
                <input class="form-control" name="ocimp_batch" value="{$config.batch|intval}">
            </div>
            <div class="form-group">
                <label>OpenCart language ID</label>
                <input class="form-control" name="ocimp_oc_lang" value="{$config.oc_lang|intval}">
                <p class="help-block">ID на езика в таблицата language (обикновено 1).</p>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="form-group">
                <label>Image import mode</label>
                <select class="form-control" name="ocimp_img_mode">
                    <option value="url" {if $config.img_mode=='url'}selected{/if}>HTTP/HTTPS (Base URL)</option>
                    <option value="fs" {if $config.img_mode=='fs'}selected{/if}>Filesystem (Base path)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Image Base URL (e.g. https://oldshop.example.com/image/)</label>
                <input class="form-control" name="ocimp_img_baseurl" value="{$config.img_baseurl|escape}">
            </div>
            <div class="form-group">
                <label>Image Base Path (e.g. /var/www/opencart/image/)</label>
                <input class="form-control" name="ocimp_img_basepath" value="{$config.img_basepath|escape}">
            </div>
        </div>
    </div>
    <button class="btn btn-primary" name="saveOcimp" value="1">
        <i class="icon-save"></i> Запази
    </button>
</form>
