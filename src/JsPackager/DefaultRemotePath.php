<?php

namespace JsPackager;

class DefaultRemotePath
{

    public $remotePath = 'public/shared';

    public function getDefaultRemotePath() {
        return getcwd() . '/' . $this->remotePath;
    }

}