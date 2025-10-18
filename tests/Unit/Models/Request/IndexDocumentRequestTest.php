<?php

declare(strict_types=1);

namespace Netfield\RagClient\Tests\Unit\Models\Request;

use PHPUnit\Framework\TestCase;
use Netfield\RagClient\Models\Request\IndexDocumentRequest;
use Netfield\RagClient\Models\Request\DocumentInfo;
use Netfield\RagClient\Exception\RagApiException;

class IndexDocumentRequestTest extends TestCase
{
    private DocumentInfo $documentInfo;

    protected function setUp(): void
    {
        $this->documentInfo = new DocumentInfo(
            title: 'Test Document',
            creationDate: '2024-08-01 19:44:00'
        );
    }

    public function testConstructorWithValidData(): void
    {
        $documentId = 'doc-123';
        $tenantId = 'tenant-456';
        $content = 'Document content here';
        $metadata = ['type' => 'invoice'];

        $request = new IndexDocumentRequest(
            $documentId,
            $tenantId,
            $this->documentInfo,
            $content,
            $metadata
        );

        $this->assertEquals($documentId, $request->getDocumentId());
        $this->assertEquals($tenantId, $request->getTenantId());
        $this->assertEquals($this->documentInfo, $request->getDocumentInfo());
        $this->assertEquals($content, $request->getContent());
        $this->assertEquals($metadata, $request->getMetadata());
    }

    public function testConstructorWithMinimalData(): void
    {
        $documentId = 'doc-123';
        $tenantId = 'tenant-456';

        $request = new IndexDocumentRequest(
            $documentId,
            $tenantId,
            $this->documentInfo
        );

        $this->assertEquals($documentId, $request->getDocumentId());
        $this->assertEquals($tenantId, $request->getTenantId());
        $this->assertEquals($this->documentInfo, $request->getDocumentInfo());
        $this->assertNull($request->getContent());
        $this->assertNull($request->getMetadata());
    }

    public function testSetDocumentIdWithValidValues(): void
    {
        $request = new IndexDocumentRequest('initial', 'tenant', $this->documentInfo);

        $request->setDocumentId('new-doc-id');
        $this->assertEquals('new-doc-id', $request->getDocumentId());

        $request->setDocumentId('  spaced-id  ');
        $this->assertEquals('spaced-id', $request->getDocumentId());

        // Test with numeric ID converted to string
        $request->setDocumentId('123');
        $this->assertEquals('123', $request->getDocumentId());
    }

    public function testSetDocumentIdWithEmptyString(): void
    {
        $this->expectException(RagApiException::class);
        $this->expectExceptionMessage('document_id is required');

        $request = new IndexDocumentRequest('initial', 'tenant', $this->documentInfo);
        $request->setDocumentId('');
    }

    public function testSetDocumentIdWithWhitespace(): void
    {
        $this->expectException(RagApiException::class);
        $this->expectExceptionMessage('document_id is required');

        $request = new IndexDocumentRequest('initial', 'tenant', $this->documentInfo);
        $request->setDocumentId('   ');
    }

    public function testConstructorWithEmptyDocumentId(): void
    {
        $this->expectException(RagApiException::class);
        $this->expectExceptionMessage('document_id is required');

        new IndexDocumentRequest('', 'tenant', $this->documentInfo);
    }

    public function testSetTenantIdWithValidValues(): void
    {
        $request = new IndexDocumentRequest('doc', 'initial-tenant', $this->documentInfo);

        $request->setTenantId('new-tenant');
        $this->assertEquals('new-tenant', $request->getTenantId());

        $request->setTenantId('  spaced-tenant  ');
        $this->assertEquals('spaced-tenant', $request->getTenantId());

        $request->setTenantId('123');
        $this->assertEquals('123', $request->getTenantId());
    }

    public function testSetTenantIdWithEmptyString(): void
    {
        $this->expectException(RagApiException::class);
        $this->expectExceptionMessage('tenant_id is required');

        $request = new IndexDocumentRequest('doc', 'initial-tenant', $this->documentInfo);
        $request->setTenantId('');
    }

    public function testSetTenantIdWithWhitespace(): void
    {
        $this->expectException(RagApiException::class);
        $this->expectExceptionMessage('tenant_id is required');

        $request = new IndexDocumentRequest('doc', 'initial-tenant', $this->documentInfo);
        $request->setTenantId('   ');
    }

    public function testConstructorWithEmptyTenantId(): void
    {
        $this->expectException(RagApiException::class);
        $this->expectExceptionMessage('tenant_id is required');

        new IndexDocumentRequest('doc', '', $this->documentInfo);
    }

    public function testSetContent(): void
    {
        $request = new IndexDocumentRequest('doc', 'tenant', $this->documentInfo);

        $content = 'New document content with OCR text';
        $request->setContent($content);
        $this->assertEquals($content, $request->getContent());

        $request->setContent(null);
        $this->assertNull($request->getContent());
    }

    public function testSetDocumentInfo(): void
    {
        $request = new IndexDocumentRequest('doc', 'tenant', $this->documentInfo);

        $newDocInfo = new DocumentInfo('New Title', '2024-08-02 10:00:00');
        $request->setDocumentInfo($newDocInfo);
        $this->assertEquals($newDocInfo, $request->getDocumentInfo());
    }

    public function testSetMetadata(): void
    {
        $request = new IndexDocumentRequest('doc', 'tenant', $this->documentInfo);

        $metadata = [
            'type' => 'contract',
            'department' => 'Legal',
            'priority' => 'high',
            'tags' => ['important', 'confidential']
        ];

        $request->setMetadata($metadata);
        $this->assertEquals($metadata, $request->getMetadata());

        $request->setMetadata(null);
        $this->assertNull($request->getMetadata());
    }

    public function testToArray(): void
    {
        $documentId = 'doc-789';
        $tenantId = 'tenant-abc';
        $content = 'Document OCR content';
        $metadata = ['category' => 'finance', 'year' => 2024];

        $request = new IndexDocumentRequest(
            $documentId,
            $tenantId,
            $this->documentInfo,
            $content,
            $metadata
        );

        $array = $request->toArray();

        $expectedArray = [
            'document_id' => $documentId,
            'tenant_id' => $tenantId,
            'document_info' => $this->documentInfo->toArray(),
            'content' => $content,
            'metadata' => $metadata
        ];

        $this->assertEquals($expectedArray, $array);
    }

    public function testToArrayWithoutOptionalFields(): void
    {
        $documentId = 'doc-minimal';
        $tenantId = 'tenant-minimal';

        $request = new IndexDocumentRequest(
            $documentId,
            $tenantId,
            $this->documentInfo
        );

        $array = $request->toArray();

        $expectedArray = [
            'document_id' => $documentId,
            'tenant_id' => $tenantId,
            'document_info' => $this->documentInfo->toArray()
        ];

        $this->assertEquals($expectedArray, $array);
        $this->assertArrayNotHasKey('content', $array);
        $this->assertArrayNotHasKey('metadata', $array);
    }

    public function testFromArray(): void
    {
        $data = [
            'document_id' => 'doc-from-array',
            'tenant_id' => 'tenant-from-array',
            'document_info' => [
                'title' => 'Array Document',
                'creation_date' => '2024-08-03 15:30:00'
            ],
            'content' => 'Content from array',
            'metadata' => ['source' => 'fromArray']
        ];

        $request = IndexDocumentRequest::fromArray($data);

        $this->assertEquals($data['document_id'], $request->getDocumentId());
        $this->assertEquals($data['tenant_id'], $request->getTenantId());
        $this->assertEquals($data['content'], $request->getContent());
        $this->assertEquals($data['metadata'], $request->getMetadata());

        $docInfo = $request->getDocumentInfo();
        $this->assertEquals($data['document_info']['title'], $docInfo->getTitle());
        $this->assertEquals($data['document_info']['creation_date'], $docInfo->getCreationDate());
    }

    public function testFromArrayWithMinimalData(): void
    {
        $data = [
            'document_id' => 'doc-minimal-array',
            'tenant_id' => 'tenant-minimal-array',
            'document_info' => [
                'title' => 'Minimal Document',
                'creation_date' => '2024-08-03 16:00:00'
            ]
        ];

        $request = IndexDocumentRequest::fromArray($data);

        $this->assertEquals($data['document_id'], $request->getDocumentId());
        $this->assertEquals($data['tenant_id'], $request->getTenantId());
        $this->assertNull($request->getContent());
        $this->assertNull($request->getMetadata());
    }

    public function testFromArrayWithInvalidDocumentId(): void
    {
        $this->expectException(RagApiException::class);
        $this->expectExceptionMessage('document_id is required');

        $data = [
            'document_id' => '',
            'tenant_id' => 'valid-tenant',
            'document_info' => [
                'title' => 'Test',
                'creation_date' => '2024-08-03 16:00:00'
            ]
        ];

        IndexDocumentRequest::fromArray($data);
    }

    /**
     * @dataProvider documentIdValidationProvider
     */
    public function testDocumentIdValidation($documentId, bool $shouldPass): void
    {
        if (!$shouldPass) {
            $this->expectException(RagApiException::class);
            $this->expectExceptionMessage('document_id is required');
        }

        $request = new IndexDocumentRequest(
            (string)$documentId,
            'tenant',
            $this->documentInfo
        );

        if ($shouldPass) {
            $this->assertEquals(trim((string)$documentId), $request->getDocumentId());
        }
    }

    public static function documentIdValidationProvider(): array
    {
        return [
            'valid_string' => ['doc-123', true],
            'valid_numeric_string' => ['123', true],
            'valid_with_spaces' => ['  doc-456  ', true],
            'valid_special_chars' => ['doc_123-abc.txt', true],
            'invalid_empty' => ['', false],
            'invalid_only_spaces' => ['   ', false],
        ];
    }

    /**
     * @dataProvider tenantIdValidationProvider
     */
    public function testTenantIdValidation($tenantId, bool $shouldPass): void
    {
        if (!$shouldPass) {
            $this->expectException(RagApiException::class);
            $this->expectExceptionMessage('tenant_id is required');
        }

        $request = new IndexDocumentRequest(
            'doc-123',
            (string)$tenantId,
            $this->documentInfo
        );

        if ($shouldPass) {
            $this->assertEquals(trim((string)$tenantId), $request->getTenantId());
        }
    }

    public static function tenantIdValidationProvider(): array
    {
        return [
            'valid_string' => ['tenant-123', true],
            'valid_numeric_string' => ['456', true],
            'valid_with_spaces' => ['  tenant-789  ', true],
            'valid_special_chars' => ['tenant_abc-def', true],
            'invalid_empty' => ['', false],
            'invalid_only_spaces' => ['   ', false],
        ];
    }
}
