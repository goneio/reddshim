<?php
namespace Gone\ReddShim;


use Predis\Command\Command;
use Predis\Command\CommandInterface;

class ReddShimSourceSelectCommand
    extends Command
    implements CommandInterface
{

    public function __construct(string $host)
    {
        $this->setArguments([$host]);
    }

    public function getId()
    {
        return 'REDDSHIM_SELECT';
    }

}