<?php

namespace Anboo\TransactionManager;

class CallbackTransactionInterface implements TransactionInterface
{
    private \Closure $callback;

    public function __construct(\Closure $callback)
    {
        $this->callback = $callback;
    }

    public function up()
    {
        call_user_func($this->callback);
    }

    public function down()
    {
    }
}
