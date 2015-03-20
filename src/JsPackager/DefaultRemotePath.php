<?php

namespace JsPackager;

class DefaultRemotePath
{

    public function getDefaultRemotePath() {
        return getcwd() . '/' . 'public/shared';
    }

}