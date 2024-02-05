<?php

class Notes_Module extends POS_Module {
    public $id = 'notes';
    public $name = "Notes";

    function register() {
        $this->register_post_type();
    }
}