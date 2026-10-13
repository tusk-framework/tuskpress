<?php

declare(strict_types=1);

namespace TuskPress\Http\Controllers\Admin;

use Psr\Http\Message\UploadedFileInterface;
use Tusk\Security\Attribute\Authenticated;
use Tusk\Contracts\Attributes\Service;
use Tusk\Web\Attribute\Route;
use Tusk\Web\Attribute\Controller;
use Tusk\Web\Http\Request;
use Tusk\Web\Http\Response;
use Psr\Http\Message\ResponseInterface;
use TuskPress\Domain\Post\Post;
use TuskPress\Domain\Post\PostId;
use TuskPress\Domain\Post\PostQuery;
use TuskPress\Domain\Post\PostStatus;
use TuskPress\Domain\Post\PostType;
use TuskPress\Infrastructure\Persistence\MySqlOptionRepository;
use TuskPress\Infrastructure\Persistence\MySqlPostRepository;
use TuskPress\Domain\User\UserId;

#[Service(scope: 'request')]
#[Authenticated]
#[Controller]
class AdminMediaController
{
    public function __construct(
        private readonly \Tusk\Security\Contract\GuardInterface $guard,
        private readonly MySqlOptionRepository $optionRepo,
        private readonly MySqlPostRepository $postRepository
    ) {}

    #[Route('/wp-admin/upload', methods: ['GET'])]
    public function index(Request $request): ResponseInterface
    {
        if (!$this->guard->check()) {
            return Response::redirect('/wp-login');
        }

        // Fetch attachments from the repository
        $query = new PostQuery(
            type: PostType::Attachment,
            perPage: 50,
            page: 1,
            orderBy: 'createdAt'
        );
        $attachments = $this->postRepository->findAll($query);

        $gridHtml = '';
        foreach ($attachments as $att) {
            $url = $att->guid ?: '/wp-content/uploads/' . $att->slug;
            $isImage = str_starts_with($att->mimeType, 'image/');
            
            if ($isImage) {
                $preview = '<img src="' . htmlspecialchars($url) . '" style="width: 100%; height: 100%; object-fit: cover;" alt="">';
            } else {
                $ext = pathinfo($att->slug, PATHINFO_EXTENSION);
                $preview = <<<HTML
                <div style="display: flex; align-items: center; justify-content: center; flex-direction: column; color: var(--text-muted); width: 100%; height: 100%;">
                    <span style="font-size: 32px; margin-bottom: 8px;">📄</span>
                    <span style="font-size: 14px; word-break: break-all; padding: 0 10px; text-align: center;">{$att->title}</span>
                </div>
                HTML;
            }

            $gridHtml .= <<<HTML
            <div style="aspect-ratio: 1; background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; position: relative;">
                {$preview}
                <div style="position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.7); padding: 6px 10px; font-size: 12px; color: #fff; backdrop-filter: blur(4px);">
                    {$att->title}
                </div>
            </div>
            HTML;
        }

        if (empty($gridHtml)) {
            $gridHtml = '<div style="grid-column: 1 / -1; text-align: center; color: var(--text-muted); padding: 40px;">No media found. Upload the first file!</div>';
        }

        $contentHtml = <<<HTML
        <div class="card" style="border: 2px dashed var(--accent); background: rgba(108,99,255,0.05); cursor: pointer; text-align: center; padding: 60px 20px; transition: all 0.3s ease; position: relative;" id="dropzone">
            <input type="file" id="file-input" multiple style="display: none;">
            <div style="font-size: 48px; margin-bottom: 16px;">☁️</div>
            <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">Drag files here to upload</h3>
            <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 24px;">or click to select from your computer</p>
            <button class="btn btn-primary" id="btn-select">Select Files</button>
            <div id="upload-progress" style="display: none; margin-top: 20px;">
                <div style="height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; overflow: hidden;">
                    <div id="upload-bar" style="height: 100%; width: 0%; background: var(--accent); transition: width 0.3s;"></div>
                </div>
                <div id="upload-text" style="font-size: 12px; margin-top: 8px; color: var(--text-muted);">Uploading...</div>
            </div>
        </div>

        <div style="margin-top: 40px; display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px;" id="media-grid">
            {$gridHtml}
        </div>
        
        <script>
            const dropzone = document.getElementById('dropzone');
            const fileInput = document.getElementById('file-input');
            const btnSelect = document.getElementById('btn-select');
            
            btnSelect.addEventListener('click', (e) => {
                e.stopPropagation();
                fileInput.click();
            });
            dropzone.addEventListener('click', () => fileInput.click());

            dropzone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropzone.style.borderColor = 'var(--accent)';
                dropzone.style.background = 'rgba(108,99,255,0.15)';
            });
            dropzone.addEventListener('dragleave', (e) => {
                dropzone.style.borderColor = 'var(--accent)';
                dropzone.style.background = 'rgba(108,99,255,0.05)';
            });
            dropzone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropzone.style.borderColor = 'var(--accent)';
                dropzone.style.background = 'rgba(108,99,255,0.05)';
                if (e.dataTransfer.files.length) uploadFiles(e.dataTransfer.files);
            });
            fileInput.addEventListener('change', () => {
                if (fileInput.files.length) uploadFiles(fileInput.files);
            });

            function uploadFiles(files) {
                const formData = new FormData();
                for (let i = 0; i < files.length; i++) {
                    formData.append('files[]', files[i]);
                }

                document.getElementById('upload-progress').style.display = 'block';
                document.getElementById('upload-bar').style.width = '30%';
                document.getElementById('upload-text').innerText = 'Uploading ' + files.length + ' file(s)...';

                fetch('/wp-admin/upload/process', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    document.getElementById('upload-bar').style.width = '100%';
                    document.getElementById('upload-text').innerText = 'Done!';
                    if (data.success) {
                        window.TuskToast(data.message || 'Files uploaded successfully!');
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        window.TuskToast(data.error || 'Upload error');
                    }
                })
                .catch(err => {
                    window.TuskToast('Failed to communicate with the server.');
                    document.getElementById('upload-progress').style.display = 'none';
                });
            }
        </script>
        HTML;

        $siteName = $this->optionRepo->get('blogname', 'TuskPress');
        $user = $this->guard->user();
        $username = $user && method_exists($user, 'getDisplayName') ? $user->getDisplayName() : 'Administrator';

        return Response::html(AdminView::render('Media Library', $contentHtml, $siteName, $username));
    }

    #[Route('/wp-admin/upload/process', methods: ['POST'])]
    public function process(Request $request): ResponseInterface
    {
        if (!$this->guard->check()) {
            return Response::json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $psrRequest = $request->getPsrRequest();
        $files = $psrRequest->getUploadedFiles();

        if (empty($files) || !isset($files['files'])) {
            return Response::json(['success' => false, 'error' => 'No files received']);
        }

        $uploadedFiles = is_array($files['files']) ? $files['files'] : [$files['files']];
        
        $uploadsDir = dirname(__DIR__, 4) . '/public/wp-content/uploads/' . date('Y/m');
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        $user = $this->guard->user();
        $userId = new UserId($user ? $user->getIdentifier() : 1);

        $savedCount = 0;

        foreach ($uploadedFiles as $file) {
            /** @var UploadedFileInterface $file */
            if ($file->getError() === UPLOAD_ERR_OK) {
                $filename = basename($file->getClientFilename());
                $safeFilename = preg_replace('/[^a-zA-Z0-9.\-_]/', '', strtolower($filename));
                $safeFilename = time() . '-' . $safeFilename;
                
                $targetPath = $uploadsDir . '/' . $safeFilename;
                $file->moveTo($targetPath);

                $mimeType = $file->getClientMediaType() ?: mime_content_type($targetPath);
                
                $relativeUrl = '/wp-content/uploads/' . date('Y/m') . '/' . $safeFilename;

                $post = new Post(
                    id:          new PostId(0),
                    title:       $filename,
                    content:     '',
                    excerpt:     '',
                    slug:        $safeFilename,
                    authorId:    $userId,
                    type:        PostType::Attachment,
                    status:      PostStatus::Publish
                );
                
                $post->mimeType = $mimeType ?: '';
                $post->guid = $relativeUrl;
                
                $this->postRepository->save($post);
                $savedCount++;
            }
        }

        if ($savedCount > 0) {
            return Response::json(['success' => true, 'message' => "{$savedCount} files uploaded!"]);
        }

        return Response::json(['success' => false, 'error' => 'Error processing files.']);
    }
}

