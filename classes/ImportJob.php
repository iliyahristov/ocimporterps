<?php
class ImportJob extends ObjectModel
{
    public $id_job;
    public $entity;
    public $status;
    public $total = 0;
    public $processed = 0;
    public $errors = 0;
    public $started_at;
    public $finished_at;
    public $context;

    public static $definition = [
        'table' => 'ocimp_job',
        'primary' => 'id_job',
        'fields' => [
            'entity' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true, 'size' => 32],
            'status' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true, 'size' => 16],
            'total' => ['type' => self::TYPE_INT],
            'processed' => ['type' => self::TYPE_INT],
            'errors' => ['type' => self::TYPE_INT],
            'started_at' => ['type' => self::TYPE_DATE],
            'finished_at' => ['type' => self::TYPE_DATE],
            'context' => ['type' => self::TYPE_HTML],
        ]
    ];
}
