<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout;

use Shopwell\Core\Framework\ContentSystem\Layout\Preset\ContentSystemLayoutProvisioner;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[AsCommand(name: 'content:blog:provision-layouts', description: 'Create the shared Blog article, video and listing layouts when no default is configured.')]
class ProvisionBlogLayoutsCommand extends Command
{
    public function __construct(
        private readonly ContentSystemLayoutProvisioner $provisioner,
        private readonly SystemConfigService $systemConfig,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach (['post' => 'Sw:BlogPost', 'video' => 'Sw:BlogVideo', 'listing' => 'Sw:BlogListing'] as $type => $preset) {
            $key = 'core.content_system.default_blog_' . $type . '_content_layout';
            if ($this->systemConfig->getString($key) !== '') {
                $output->writeln('Keeping the configured Blog ' . $type . ' layout.');

                continue;
            }

            $id = $this->provisioner->provisionFromPreset('Blog ' . $type, $preset, $type === 'listing' ? 'none' : 'blog', Context::createCLIContext());
            $this->systemConfig->set($key, $id);
            $output->writeln('Created Blog ' . $type . ' layout: ' . $id);
        }

        return self::SUCCESS;
    }
}
