<?php
declare(strict_types = 1);
namespace RCS\WP\BgProcess;

interface BgProcessInterface
{
    /**
     * Add a task to the queue
     *
     * @param BgTaskInterface $task
     *
     * @return $this

     * @see \WP_Background_Process::push_to_queue()
     */
    public function pushToQueue(BgTaskInterface $task): self;

    /**
     * @see \WP_Background_Process::push_to_queue()
     *
     * @return void
     */
    public function save();

    /**
     * @see \WP_Background_Process::push_to_queue()
     *
     * @return void
     */
    public function dispatch();
}
