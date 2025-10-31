<?php
class OcimpLogger
{
    public static function add($id_job, $message)
    {
        PrestaShopLogger::addLog('[OCIMP #'.(int)$id_job.'] '.$message, 1, null, 'Ocimporterps', $id_job, true);
    }
}
