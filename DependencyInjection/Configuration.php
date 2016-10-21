<?php

/*
 * This file is part of the CLTissueBundle.
 *
 * (c) Cas Leentfaar <info@casleentfaar.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CL\Bundle\TissueBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Configuration implements ConfigurationInterface
{
    /**
     * @var array
     */
    private $supportedAdapters = ['clamav', 'clamavphp'];

    /**
     * @var OptionsResolver[]
     */
    private $resolvers = [];

    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $tb = new TreeBuilder();
        $self = $this;
        $rootNode = $tb->root('cl_tissue');

        $rootNode
            ->children()
                ->arrayNode('adapter')
                    ->isRequired()
                    ->beforeNormalization()
                        ->ifNull()
                        ->then(function($v) use ($self) {
                            $retVal = ['alias' => 'clamav'];
                            if ($resolver = $self->getResolver($retVal['alias'])) {
                                $retVal['options'] = $resolver->resolve([]);
                            }

                            return $retVal;
                        })
                    ->end()
                    ->beforeNormalization()
                        ->ifString()
                        ->then(function($v) use ($self) {
                            $retVal = ['alias' => $v];
                            if ($resolver = $self->getResolver($retVal['alias'])) {
                                $retVal['options'] = $resolver->resolve([]);
                            }

                            return $retVal;
                        })
                    ->end()
                    ->validate()
                        ->ifArray()
                        ->then(function ($v) {
                            if ($v['alias'] === 'clamavphp' && !class_exists('\CL\Tissue\Adapter\ClamAVPHP\ClamAVPHPAdapter')) {
                                throw new InvalidConfigurationException('If you want to use the `clamavphp` adapter, you need to add the `cleentfaar/tissue-clamavphp-adapter` package to your composer.json');
                            }

                            return $v;
                        })
                    ->end()
                    ->children()
                        ->scalarNode('alias')->isRequired()->defaultValue('clamav')->end()
                        ->variableNode('options')->defaultValue([])->end()
                        // ...
                    ->end()
                ->end()
            ->end()
        ;

        return $tb;
    }

    /**
     * @param string $alias
     *
     * @return OptionsResolver|null
     */
    private function getResolver($alias)
    {
        if (!in_array($alias, $this->supportedAdapters)) {
            return null;
        }

        if (!isset($this->resolvers[$alias])) {
            $this->resolvers[$alias] = $this->createResolver($alias);
        }

        return $this->resolvers[$alias];
    }

    /**
     * @param string $alias
     *
     * @return OptionsResolver
     */
    private function createResolver($alias)
    {
        $resolver = new OptionsResolver();
        switch ($alias) {
            case 'clamav':
                $resolver->setDefaults([
                    'bin'      => '/usr/bin/clamdscan',
                    'database' => null,
                ]);
                $resolver
                    ->setAllowedTypes('bin', ['string'])
                    ->setAllowedTypes('database', ['string', 'null'])
                ;
                break;
            default:
                break;
        }

        return $resolver;
    }
}
