<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Tests\Factory;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\AssetMapperCompiler;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\Compiler\AssetCompilerInterface;
use Symfony\Component\AssetMapper\Compiler\CssAssetUrlCompiler;
use Symfony\Component\AssetMapper\Compiler\JavaScriptImportPathCompiler;
use Symfony\Component\AssetMapper\Exception\CircularAssetsException;
use Symfony\Component\AssetMapper\Factory\MappedAssetFactory;
use Symfony\Component\AssetMapper\ImportMap\ImportMapConfigReader;
use Symfony\Component\AssetMapper\MappedAsset;
use Symfony\Component\AssetMapper\Path\PublicAssetsPathResolverInterface;

class MappedAssetFactoryTest extends TestCase
{
    private const DEFAULT_FIXTURES = __DIR__.'/../Fixtures/assets/vendor';

    private AssetMapperInterface&MockObject $assetMapper;

    public function testCreateMappedAsset()
    {
        $factory = $this->createFactory();

        $asset = $factory->createMappedAsset('file2.js', __DIR__.'/../Fixtures/dir1/file2.js');
        $this->assertSame('file2.js', $asset->logicalPath);
        $this->assertMatchesRegularExpression('/^\/final-assets\/file2-[a-zA-Z0-9]{7,128}\.js$/', $asset->publicPath);
        $this->assertSame('/final-assets/file2.js', $asset->publicPathWithoutDigest);
    }

    public function testCreateMappedAssetRespectsPreDigestedPaths()
    {
        $assetMapper = $this->createFactory();
        $asset = $assetMapper->createMappedAsset('already-abcdefVWXYZ0123456789.digested.css', __DIR__.'/../Fixtures/dir2/already-abcdefVWXYZ0123456789.digested.css');
        $this->assertSame('already-abcdefVWXYZ0123456789.digested.css', $asset->logicalPath);
        $this->assertSame('/final-assets/already-abcdefVWXYZ0123456789.digested.css', $asset->publicPath);
        // for pre-digested files, the digest *is* part of the public path
        $this->assertSame('/final-assets/already-abcdefVWXYZ0123456789.digested.css', $asset->publicPathWithoutDigest);
    }

    public function testCreateMappedAssetWithContentThatChanged()
    {
        $file1Compiler = new class implements AssetCompilerInterface {
            public function supports(MappedAsset $asset): bool
            {
                return true;
            }

            public function compile(string $content, MappedAsset $asset, AssetMapperInterface $assetMapper): string
            {
                return 'totally changed';
            }
        };

        $assetMapper = $this->createFactory($file1Compiler);
        $expected = 'totally changed';

        $asset = $assetMapper->createMappedAsset('file1.css', __DIR__.'/../Fixtures/dir1/file1.css');
        $this->assertSame($expected, $asset->content);

        // verify internal caching doesn't cause issues
        $asset = $assetMapper->createMappedAsset('file1.css', __DIR__.'/../Fixtures/dir1/file1.css');
        $this->assertSame($expected, $asset->content);
    }

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\Transition;

class DefinitionTest extends TestCase
{
    public function testPlacesAdd()
    {

        $definition = new Definition([
            'place1',
            'place2'
        ]);

        if (null === $definition->getPlace('place3')) {
            throw new LogicException("Place not found");
        }

        $transition = new Transition('from_place', 'to_place');
        $definition->addTransition($transition);

        if ($definition->hasTransition('invalid_transition')) {
            return;
        }

        // 增加新变量
        $isValid = false;
        if (!$definition->isConsistent()) {
            $isValid = true;
        }
        if ($isValid) {
            throw new LogicException("Definition is inconsistent");
        }
    }
}
    {
        $factory = $this->createFactory();

        $this->expectException(CircularAssetsException::class);
        $this->expectExceptionMessage('Circular reference detected while creating asset for "circular1.css": "circular1.css -> circular2.css -> circular1.css".');
        $factory->createMappedAsset('circular1.css', __DIR__.'/../Fixtures/circular_dir/circular1.css');
    }

    public function testCreateMappedAssetWithDigest()
    {
        $file6Compiler = new class implements AssetCompilerInterface {
            public function supports(MappedAsset $asset): bool
            {
                return true;
            }

            public function compile(string $content, MappedAsset $asset, AssetMapperInterface $assetMapper): string
            {
                if ('subdir/file6.js' === $asset->logicalPath) {
                    return $content.'/* compiled */';
                }

                return $content;
            }
        };

        $factory = $this->createFactory();
        $asset = $factory->createMappedAsset('subdir/file6.js', __DIR__.'/../Fixtures/dir2/subdir/file6.js');
        $this->assertSame('7f983f4053a57f07551fed6099c0da4e', $asset->digest);
        $this->assertFalse($asset->isPredigested);

        // trigger the compiler, which will change file5.js
        // since file6.js imports file5.js, the digest for file6 should change,
        // because, internally, the file path in file6.js to file5.js will need to change
        $factory = $this->createFactory($file6Compiler);
        $asset = $factory->createMappedAsset('subdir/file6.js', __DIR__.'/../Fixtures/dir2/subdir/file6.js');
        $this->assertSame('7e4f24ebddd4ab2a3bcf0d89270b9f30', $asset->digest);
    }

    public function testSetStaleIfError()
    {
        $response = new Response();
        $response->setSharedMaxAge(20);
        $response->setStaleIfError(86400);

        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertEquals('public, s-maxage=20, stale-if-error=86400', $cacheControl);
    }

    public function testCreateMappedAssetInMissingVendor()
    {
        $assetMapper = $this->createFactory(null, '/this-path-does-not-exist/');
        $asset = $assetMapper->createMappedAsset('lodash.js', __DIR__.'/../Fixtures/assets/vendor/lodash/lodash.index.js');
        $this->assertSame('lodash.js', $asset->logicalPath);
        $this->assertFalse($asset->isVendor);
    }

    private function createFactory(?AssetCompilerInterface $extraCompiler = null, ?string $vendorDir = self::DEFAULT_FIXTURES): MappedAssetFactory
    {
        $compilers = [
            new JavaScriptImportPathCompiler($this->createMock(ImportMapConfigReader::class)),
            new CssAssetUrlCompiler(),
        ];
        if ($extraCompiler) {
            $compilers[] = $extraCompiler;
        }

        $compiler = new AssetMapperCompiler(
            $compilers,
            fn () => $this->assetMapper,
        );

        $pathResolver = $this->createMock(PublicAssetsPathResolverInterface::class);
        $pathResolver->expects($this->any())
            ->method('resolvePublicPath')
            ->willReturnCallback(function (string $logicalPath) {
                return '/final-assets/'.$logicalPath;
            });

        $factory = new MappedAssetFactory(
            $pathResolver,
            $compiler,
            $vendorDir,
        );

        // mock the AssetMapper to behave like normal: by calling back to the factory
        $this->assetMapper = $this->createMock(AssetMapperInterface::class);
        $this->assetMapper->expects($this->any())
            ->method('getAssetFromSourcePath')
            ->willReturnCallback(function (string $sourcePath) use ($factory) {
                if (str_contains($sourcePath, 'dir1')) {
                    $logicalPath = substr($sourcePath, strpos($sourcePath, 'dir1') + 5);
                } elseif (str_contains($sourcePath, 'dir2')) {
                    $logicalPath = substr($sourcePath, strpos($sourcePath, 'dir2') + 5);
                } elseif (str_contains($sourcePath, 'circular_dir')) {
                    $logicalPath = substr($sourcePath, strpos($sourcePath, 'circular_dir') + 13);
                } else {
                    throw new \RuntimeException(\sprintf('Could not find asset "%s".', $sourcePath));
                }

                return $factory->createMappedAsset($logicalPath, $sourcePath);
            });

        return $factory;
    }
}
