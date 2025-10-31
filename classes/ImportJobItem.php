<?php
class ImportJobItem extends ObjectModel
{
    public $id_item;
    public $id_job;
    public $source_id;
    public $status;
    public $message;

    public static $definition = [
        'table' => 'ocimp_job_item',
        'primary' => 'id_item',
        'fields' => [
            'id_job' => ['type' => self::TYPE_INT, 'required' => true],
            'source_id' => ['type' => self::TYPE_INT, 'required' => true],
            'status' => ['type' => self::TYPE_STRING, 'size' => 16, 'required' => true],
            'message' => ['type' => self::TYPE_HTML],
        ]
    ];
}
