<?php

namespace Anboo\TransactionManager;

interface TransactionInterface
{
    public function up();
    public function down();
}
