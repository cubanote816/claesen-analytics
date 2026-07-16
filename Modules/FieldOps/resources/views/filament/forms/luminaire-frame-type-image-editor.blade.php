@php
    /**
     * @var array{uploadUrl: string} $data
     */
    $data = array_merge([
        'uploadUrl' => '',
    ], $getViewData());

    $currentImage = $getState();
    $statePath = $getStatePath();
    $currentImageLabel = __('fieldops::resource.catalogs.frame_type_editor.preview_label');
    $currentImageEmptyLabel = __('fieldops::resource.catalogs.frame_type_editor.preview_empty');
@endphp

@once
    @push('styles')
        <style>
            .fieldops-luminaire-frame-type-image-editor {
                overflow: hidden;
                border: 1px solid rgba(148, 163, 184, 0.18);
                border-radius: 1.25rem;
                background:
                    radial-gradient(circle at top left, rgba(0, 174, 239, 0.08), transparent 35%),
                    linear-gradient(180deg, #ffffff 0%, #f7fbfe 100%);
                box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
            }

            .dark .fieldops-luminaire-frame-type-image-editor {
                border-color: rgba(255, 255, 255, 0.08);
                background:
                    radial-gradient(circle at top left, rgba(0, 174, 239, 0.14), transparent 30%),
                    linear-gradient(180deg, #171725 0%, #11111a 100%);
                box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.08), 0 24px 60px -18px rgba(0, 0, 0, 0.62);
            }

            .fieldops-luminaire-frame-type-image-editor__header {
                display: flex;
                justify-content: space-between;
                gap: 1rem;
                padding: 1.15rem 1.2rem 0.95rem;
                border-bottom: 1px solid rgba(148, 163, 184, 0.14);
            }

            .dark .fieldops-luminaire-frame-type-image-editor__header {
                border-bottom-color: rgba(255, 255, 255, 0.08);
            }

            .fieldops-luminaire-frame-type-image-editor__eyebrow {
                color: #009bd6;
                font-size: 0.68rem;
                font-weight: 800;
                letter-spacing: 0.18em;
                text-transform: uppercase;
            }

            .fieldops-luminaire-frame-type-image-editor__title {
                margin-top: 0.35rem;
                color: #0f172a;
                font-size: 1.05rem;
                font-weight: 800;
                letter-spacing: -0.02em;
            }

            .dark .fieldops-luminaire-frame-type-image-editor__title {
                color: #f8fafc;
            }

            .fieldops-luminaire-frame-type-image-editor__description {
                margin-top: 0.35rem;
                color: #64748b;
                font-size: 0.9rem;
                line-height: 1.45;
            }

            .dark .fieldops-luminaire-frame-type-image-editor__description {
                color: #94a3b8;
            }

            .fieldops-luminaire-frame-type-image-editor__pill {
                display: inline-flex;
                align-items: center;
                align-self: flex-start;
                min-height: 2rem;
                padding: 0.35rem 0.75rem;
                border: 1px solid rgba(0, 174, 239, 0.22);
                border-radius: 999px;
                background: rgba(0, 174, 239, 0.08);
                color: #006f9b;
                font-size: 0.86rem;
                font-weight: 700;
                white-space: nowrap;
            }

            .dark .fieldops-luminaire-frame-type-image-editor__pill {
                border-color: rgba(0, 174, 239, 0.24);
                background: rgba(0, 174, 239, 0.12);
                color: #7dd3fc;
            }

            .fieldops-luminaire-frame-type-image-editor__body {
                display: grid;
                grid-template-columns: minmax(0, 1fr);
                gap: 1rem;
                padding: 1rem 1.2rem 1.2rem;
            }

            @media (min-width: 1024px) {
                .fieldops-luminaire-frame-type-image-editor__body {
                    grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
                    align-items: start;
                }
            }

            .fieldops-luminaire-frame-type-image-editor__panel {
                display: flex;
                flex-direction: column;
                gap: 0.9rem;
                padding: 1rem;
                border: 1px solid rgba(148, 163, 184, 0.14);
                border-radius: 1rem;
                background: rgba(255, 255, 255, 0.55);
            }

            .dark .fieldops-luminaire-frame-type-image-editor__panel {
                border-color: rgba(255, 255, 255, 0.08);
                background: rgba(255, 255, 255, 0.03);
            }

            .fieldops-luminaire-frame-type-image-editor__panel-title {
                color: #0f172a;
                font-size: 0.95rem;
                font-weight: 800;
                line-height: 1.25;
            }

            .dark .fieldops-luminaire-frame-type-image-editor__panel-title {
                color: #f8fafc;
            }

            .fieldops-luminaire-frame-type-image-editor__panel-copy {
                color: #64748b;
                font-size: 0.86rem;
                line-height: 1.45;
            }

            .dark .fieldops-luminaire-frame-type-image-editor__panel-copy {
                color: #94a3b8;
            }

            .fieldops-luminaire-frame-type-image-editor__file {
                display: block;
                width: 100%;
                padding: 0.8rem;
                border: 1px dashed rgba(148, 163, 184, 0.28);
                border-radius: 0.9rem;
                background: rgba(255, 255, 255, 0.7);
                color: #94a3b8;
                font-size: 0.88rem;
            }

            .dark .fieldops-luminaire-frame-type-image-editor__file {
                border-color: rgba(255, 255, 255, 0.1);
                background: rgba(15, 23, 42, 0.45);
                color: #94a3b8;
            }

            .fieldops-luminaire-frame-type-image-editor__preview {
                overflow: hidden;
                border: 1px solid rgba(148, 163, 184, 0.14);
                border-radius: 1rem;
                background:
                    linear-gradient(135deg, rgba(0, 174, 239, 0.08), rgba(255, 255, 255, 0.55)),
                    radial-gradient(circle at center, rgba(148, 163, 184, 0.18), transparent 66%);
            }

            .dark .fieldops-luminaire-frame-type-image-editor__preview {
                border-color: rgba(255, 255, 255, 0.08);
                background:
                    linear-gradient(135deg, rgba(0, 174, 239, 0.18), rgba(255, 255, 255, 0.04)),
                    radial-gradient(circle at center, rgba(255, 255, 255, 0.08), transparent 66%);
            }

            .fieldops-luminaire-frame-type-image-editor__preview-empty {
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 14rem;
                padding: 1rem;
                color: #64748b;
                font-size: 0.9rem;
                line-height: 1.5;
                text-align: center;
            }

            .dark .fieldops-luminaire-frame-type-image-editor__preview-empty {
                color: #94a3b8;
            }

            .fieldops-luminaire-frame-type-image-editor__preview-image {
                display: block;
                width: 100%;
                min-height: 14rem;
                object-fit: contain;
                background: #020617;
            }

            .fieldops-luminaire-frame-type-image-editor__toolbar {
                display: flex;
                flex-wrap: wrap;
                gap: 0.55rem;
            }

            .fieldops-luminaire-frame-type-image-editor__tool-group {
                display: flex;
                flex-wrap: wrap;
                gap: 0.45rem;
                padding: 0.2rem;
                border: 1px solid rgba(148, 163, 184, 0.16);
                border-radius: 0.9rem;
                background: rgba(255, 255, 255, 0.45);
            }

            .dark .fieldops-luminaire-frame-type-image-editor__tool-group {
                border-color: rgba(255, 255, 255, 0.08);
                background: rgba(255, 255, 255, 0.03);
            }

            .fieldops-luminaire-frame-type-image-editor__button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 2.4rem;
                padding: 0.45rem 0.8rem;
                border: 1px solid rgba(148, 163, 184, 0.18);
                border-radius: 0.75rem;
                background: rgba(15, 23, 42, 0.04);
                color: #0f172a;
                font-size: 0.84rem;
                font-weight: 700;
                transition: transform 150ms ease, border-color 150ms ease, background-color 150ms ease;
            }

            .fieldops-luminaire-frame-type-image-editor__button:hover {
                transform: translateY(-1px);
                border-color: rgba(14, 165, 233, 0.34);
            }

            .dark .fieldops-luminaire-frame-type-image-editor__button {
                border-color: rgba(255, 255, 255, 0.08);
                background: rgba(255, 255, 255, 0.04);
                color: #e2e8f0;
            }

            .fieldops-luminaire-frame-type-image-editor__button--primary {
                border-color: rgba(14, 165, 233, 0.36);
                background: rgba(14, 165, 233, 0.12);
                color: #075985;
            }

            .fieldops-luminaire-frame-type-image-editor__button--tool {
                min-width: 5.75rem;
                background: transparent;
            }

            .fieldops-luminaire-frame-type-image-editor__button--tool.is-active {
                border-color: rgba(14, 165, 233, 0.44);
                background: rgba(14, 165, 233, 0.16);
                color: #0369a1;
            }

            .dark .fieldops-luminaire-frame-type-image-editor__button--tool.is-active {
                color: #7dd3fc;
            }

            .dark .fieldops-luminaire-frame-type-image-editor__button--primary {
                border-color: rgba(56, 189, 248, 0.28);
                background: rgba(56, 189, 248, 0.14);
                color: #7dd3fc;
            }

            .fieldops-luminaire-frame-type-image-editor__button:disabled {
                cursor: not-allowed;
                opacity: 0.5;
                transform: none;
            }

            .fieldops-luminaire-frame-type-image-editor__canvas-shell {
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
            }

            .fieldops-luminaire-frame-type-image-editor__canvas {
                width: 100%;
                aspect-ratio: 16 / 9;
                border: 1px solid rgba(148, 163, 184, 0.18);
                border-radius: 1rem;
                background:
                    linear-gradient(135deg, rgba(2, 6, 23, 0.92), rgba(15, 23, 42, 0.92)),
                    radial-gradient(circle at top left, rgba(0, 174, 239, 0.14), transparent 34%);
                box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.04);
                touch-action: none;
            }

            .fieldops-luminaire-frame-type-image-editor__status {
                color: #64748b;
                font-size: 0.84rem;
                line-height: 1.45;
            }

            .dark .fieldops-luminaire-frame-type-image-editor__status {
                color: #94a3b8;
            }

            .fieldops-luminaire-frame-type-image-editor__alert {
                display: none;
                padding: 0.75rem 0.9rem;
                border: 1px solid rgba(248, 113, 113, 0.24);
                border-radius: 0.85rem;
                background: rgba(248, 113, 113, 0.1);
                color: #fca5a5;
                font-size: 0.85rem;
                line-height: 1.45;
            }

            .fieldops-luminaire-frame-type-image-editor__alert[data-visible="true"] {
                display: block;
            }

            .fieldops-luminaire-frame-type-image-editor__hidden {
                display: none;
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('fieldopsLuminaireFrameTypeImageEditor', (config) => ({
                uploadUrl: config.uploadUrl,
                currentImage: config.currentImage || '',
                isUploading: false,
                isDrawing: false,
                tool: 'freehand',
                startPoint: null,
                snapshot: null,
                hasDrawn: false,
                statusMessage: config.currentImage
                    ? config.labels.loaded
                        : config.labels.empty,
                    errorMessage: '',
                    labels: config.labels,
                    ctx: null,
                    canvas: null,
                    drawingColor: '#f8fafc',
                    drawingWidth: 4,
                    toolLabel(tool) {
                        switch (tool) {
                            case 'line':
                                return config.labels.tool_line;
                            case 'rect':
                                return config.labels.tool_rect;
                            case 'circle':
                                return config.labels.tool_circle;
                            default:
                                return config.labels.tool_freehand;
                        }
                    },
                    setTool(tool) {
                        this.tool = tool;
                        this.errorMessage = '';
                        this.statusMessage = `${config.labels.tool_label}: ${this.toolLabel(tool)}`;
                    },
                    init() {
                        this.canvas = this.$refs.canvas;
                        this.ctx = this.canvas.getContext('2d');
                        this.ctx.lineCap = 'round';
                        this.ctx.lineJoin = 'round';
                        this.ctx.strokeStyle = this.drawingColor;
                        this.ctx.lineWidth = this.drawingWidth;
                        this.ctx.imageSmoothingEnabled = true;

                        if (this.currentImage) {
                            this.loadImageOnCanvas(this.currentImage);
                        } else {
                            this.paintBlankCanvas();
                            this.statusMessage = `${config.labels.tool_label}: ${this.toolLabel(this.tool)}`;
                        }
                    },
                    setHiddenValue(url) {
                        this.$refs.stateInput.value = url;
                        this.$refs.stateInput.dispatchEvent(new Event('input', { bubbles: true }));
                        this.$refs.stateInput.dispatchEvent(new Event('change', { bubbles: true }));
                    },
                    async handleUpload(event) {
                        const file = event.target.files && event.target.files[0] ? event.target.files[0] : null;

                        if (!file) {
                            return;
                        }

                        event.target.value = '';

                        try {
                            this.isUploading = true;
                            this.errorMessage = '';
                            this.statusMessage = this.labels.uploading;
                            const url = await this.uploadFile(file);
                            this.currentImage = url;
                            this.setHiddenValue(url);
                            await this.loadImageOnCanvas(url);
                            this.statusMessage = this.labels.uploaded;
                        } catch (error) {
                            this.errorMessage = error?.message || this.labels.error;
                            this.statusMessage = '';
                        } finally {
                            this.isUploading = false;
                        }
                    },
                    async saveDrawing() {
                        try {
                            this.isUploading = true;
                            this.errorMessage = '';
                            this.statusMessage = this.labels.saving;
                            const blob = await this.canvasToBlob();
                            const url = await this.uploadFile(blob, 'luminaire-frame-type-drawing.png');
                            this.currentImage = url;
                            this.setHiddenValue(url);
                            await this.loadImageOnCanvas(url);
                            this.statusMessage = this.labels.saved;
                        } catch (error) {
                            this.errorMessage = error?.message || this.labels.error;
                            this.statusMessage = '';
                        } finally {
                            this.isUploading = false;
                        }
                    },
                    async uploadFile(file, fileName = null) {
                        const formData = new FormData();
                        const payload = fileName ? new File([file], fileName, { type: file.type || 'image/png' }) : file;

                        formData.append('file', payload);

                        const response = await fetch(this.uploadUrl, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': config.csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: formData,
                        });

                        const json = await response.json().catch(() => null);

                        if (!response.ok || !json || !json.success || !json.data?.url) {
                            throw new Error(json?.message || this.labels.error);
                        }

                        return json.data.url;
                    },
                    paintBlankCanvas() {
                        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                        this.statusMessage = this.labels.empty;
                    },
                    async loadCurrentIntoCanvas() {
                        if (!this.currentImage) {
                            return;
                        }

                        await this.loadImageOnCanvas(this.currentImage);
                        this.statusMessage = this.labels.loaded;
                    },
                    async loadImageOnCanvas(url) {
                        return await new Promise((resolve, reject) => {
                            const image = new Image();
                            image.crossOrigin = 'anonymous';
                            image.onload = () => {
                                this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                                const canvasWidth = this.canvas.width;
                                const canvasHeight = this.canvas.height;
                                const scale = Math.min(canvasWidth / image.width, canvasHeight / image.height);
                                const drawWidth = image.width * scale;
                                const drawHeight = image.height * scale;
                                const drawX = (canvasWidth - drawWidth) / 2;
                                const drawY = (canvasHeight - drawHeight) / 2;

                                this.ctx.drawImage(image, drawX, drawY, drawWidth, drawHeight);
                                resolve();
                            };
                            image.onerror = () => reject(new Error(this.labels.error));
                            image.src = url;
                        });
                    },
                    pointerPosition(event) {
                        const rect = this.canvas.getBoundingClientRect();
                        const scaleX = this.canvas.width / rect.width;
                        const scaleY = this.canvas.height / rect.height;

                        return {
                            x: (event.clientX - rect.left) * scaleX,
                            y: (event.clientY - rect.top) * scaleY,
                        };
                    },
                    saveSnapshot() {
                        this.snapshot = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
                    },
                    restoreSnapshot() {
                        if (this.snapshot) {
                            this.ctx.putImageData(this.snapshot, 0, 0);
                        }
                    },
                    drawFreehand(point, isStart = false) {
                        if (isStart) {
                            this.ctx.beginPath();
                            this.ctx.moveTo(point.x, point.y);
                            this.ctx.lineTo(point.x + 0.1, point.y + 0.1);
                            this.ctx.stroke();
                            return;
                        }

                        this.ctx.lineTo(point.x, point.y);
                        this.ctx.stroke();
                    },
                    drawLine(point) {
                        this.ctx.beginPath();
                        this.ctx.moveTo(this.startPoint.x, this.startPoint.y);
                        this.ctx.lineTo(point.x, point.y);
                        this.ctx.stroke();
                    },
                    drawRect(point) {
                        const x = Math.min(this.startPoint.x, point.x);
                        const y = Math.min(this.startPoint.y, point.y);
                        const width = Math.abs(point.x - this.startPoint.x);
                        const height = Math.abs(point.y - this.startPoint.y);

                        this.ctx.beginPath();
                        this.ctx.strokeRect(x, y, width, height);
                    },
                    drawCircle(point) {
                        const radius = Math.hypot(point.x - this.startPoint.x, point.y - this.startPoint.y);

                        this.ctx.beginPath();
                        this.ctx.arc(this.startPoint.x, this.startPoint.y, radius, 0, Math.PI * 2);
                        this.ctx.stroke();
                    },
                    renderShape(point) {
                        switch (this.tool) {
                            case 'line':
                                this.drawLine(point);
                                break;
                            case 'rect':
                                this.drawRect(point);
                                break;
                            case 'circle':
                                this.drawCircle(point);
                                break;
                            default:
                                this.drawFreehand(point);
                                break;
                        }
                    },
                    startDrawing(event) {
                        if (event.button !== 0 && event.pointerType === 'mouse') {
                            return;
                        }

                        this.isDrawing = true;
                        this.hasDrawn = true;
                        this.errorMessage = '';
                        this.startPoint = this.pointerPosition(event);
                        this.saveSnapshot();

                        if (this.tool === 'freehand') {
                            this.drawFreehand(this.startPoint, true);
                        } else {
                            this.restoreSnapshot();
                            this.renderShape(this.startPoint);
                        }

                        this.canvas.setPointerCapture(event.pointerId);
                    },
                    draw(event) {
                        if (!this.isDrawing) {
                            return;
                        }

                        const point = this.pointerPosition(event);
                        if (this.tool === 'freehand') {
                            this.drawFreehand(point);
                            return;
                        }

                        this.restoreSnapshot();
                        this.renderShape(point);
                    },
                    stopDrawing(event) {
                        if (!this.isDrawing) {
                            return;
                        }

                        const point = this.pointerPosition(event);
                        if (this.tool !== 'freehand') {
                            this.restoreSnapshot();
                            this.renderShape(point);
                        }

                        this.isDrawing = false;
                        this.ctx.closePath();
                        this.snapshot = null;
                        this.startPoint = null;

                        if (event.pointerId !== undefined && this.canvas.hasPointerCapture(event.pointerId)) {
                            this.canvas.releasePointerCapture(event.pointerId);
                        }
                    },
                    async canvasToBlob() {
                        return await new Promise((resolve, reject) => {
                            this.canvas.toBlob((blob) => {
                                if (!blob) {
                                    reject(new Error(this.labels.error));
                                    return;
                                }

                                resolve(blob);
                            }, 'image/png');
                        });
                    },
                }));
            });
        </script>
    @endpush
@endonce

<div
    class="fieldops-luminaire-frame-type-image-editor"
    x-data="fieldopsLuminaireFrameTypeImageEditor({
        uploadUrl: @js($data['uploadUrl']),
        currentImage: @js($currentImage),
        csrfToken: @js(csrf_token()),
        labels: {
            empty: @js(__('fieldops::resource.catalogs.frame_type_editor.preview_empty')),
            loaded: @js(__('fieldops::resource.catalogs.frame_type_editor.loaded')),
            uploading: @js(__('fieldops::resource.catalogs.frame_type_editor.uploading')),
            uploaded: @js(__('fieldops::resource.catalogs.frame_type_editor.uploaded')),
            saving: @js(__('fieldops::resource.catalogs.frame_type_editor.save_drawing')),
            saved: @js(__('fieldops::resource.catalogs.frame_type_editor.saved')),
            error: @js(__('fieldops::resource.catalogs.frame_type_editor.error')),
            tool_label: @js(__('fieldops::resource.catalogs.frame_type_editor.tool_label')),
            tool_freehand: @js(__('fieldops::resource.catalogs.frame_type_editor.tool_freehand')),
            tool_line: @js(__('fieldops::resource.catalogs.frame_type_editor.tool_line')),
            tool_rect: @js(__('fieldops::resource.catalogs.frame_type_editor.tool_rect')),
            tool_circle: @js(__('fieldops::resource.catalogs.frame_type_editor.tool_circle')),
        }
    })"
    x-init="init()"
>
    <input
        x-ref="stateInput"
        class="fieldops-luminaire-frame-type-image-editor__hidden"
        type="hidden"
        name="{{ $statePath }}"
        wire:model.live="{{ $statePath }}"
        value="{{ $currentImage }}"
    >

    <div class="fieldops-luminaire-frame-type-image-editor__header">
        <div>
            <div class="fieldops-luminaire-frame-type-image-editor__eyebrow">
                {{ __('fieldops::resource.catalogs.frame_type_editor.title') }}
            </div>
            <div class="fieldops-luminaire-frame-type-image-editor__title">
                {{ __('fieldops::resource.catalogs.frame_type_editor.title') }}
            </div>
            <div class="fieldops-luminaire-frame-type-image-editor__description">
                {{ __('fieldops::resource.catalogs.frame_type_editor.helper') }}
            </div>
        </div>

        <div class="fieldops-luminaire-frame-type-image-editor__pill">
            {{ $currentImage ? $currentImageLabel : $currentImageEmptyLabel }}
        </div>
    </div>

    <div class="fieldops-luminaire-frame-type-image-editor__body">
        <div class="fieldops-luminaire-frame-type-image-editor__panel">
            <div>
                <div class="fieldops-luminaire-frame-type-image-editor__panel-title">
                    {{ __('fieldops::resource.catalogs.frame_type_editor.upload_title') }}
                </div>
                <div class="fieldops-luminaire-frame-type-image-editor__panel-copy">
                    {{ __('fieldops::resource.catalogs.frame_type_editor.upload_help') }}
                </div>
            </div>

            <input
                type="file"
                class="fieldops-luminaire-frame-type-image-editor__file"
                accept="image/png,image/jpeg,image/webp"
                x-on:change="handleUpload($event)"
                :disabled="isUploading"
            >

            <div class="fieldops-luminaire-frame-type-image-editor__preview">
                <template x-if="currentImage">
                    <img
                        :src="currentImage"
                        alt="{{ __('fieldops::resource.catalogs.frame_type_editor.preview_label') }}"
                        class="fieldops-luminaire-frame-type-image-editor__preview-image"
                    >
                </template>

                <template x-if="!currentImage">
                    <div class="fieldops-luminaire-frame-type-image-editor__preview-empty">
                        {{ __('fieldops::resource.catalogs.frame_type_editor.preview_empty') }}
                    </div>
                </template>
            </div>

            <div class="fieldops-luminaire-frame-type-image-editor__status" x-text="statusMessage"></div>
            <div class="fieldops-luminaire-frame-type-image-editor__alert" :data-visible="errorMessage ? 'true' : 'false'" x-text="errorMessage"></div>
        </div>

        <div class="fieldops-luminaire-frame-type-image-editor__panel">
            <div>
                <div class="fieldops-luminaire-frame-type-image-editor__panel-title">
                    {{ __('fieldops::resource.catalogs.frame_type_editor.draw_title') }}
                </div>
                <div class="fieldops-luminaire-frame-type-image-editor__panel-copy">
                    {{ __('fieldops::resource.catalogs.frame_type_editor.draw_help') }}
                </div>
            </div>

            <div class="fieldops-luminaire-frame-type-image-editor__toolbar">
                <div class="fieldops-luminaire-frame-type-image-editor__tool-group" role="group" aria-label="{{ __('fieldops::resource.catalogs.frame_type_editor.tool_label') }}">
                    <button
                        type="button"
                        class="fieldops-luminaire-frame-type-image-editor__button fieldops-luminaire-frame-type-image-editor__button--tool"
                        :class="tool === 'freehand' ? 'is-active' : ''"
                        x-on:click="setTool('freehand')"
                        :aria-pressed="tool === 'freehand'"
                    >
                        {{ __('fieldops::resource.catalogs.frame_type_editor.tool_freehand') }}
                    </button>

                    <button
                        type="button"
                        class="fieldops-luminaire-frame-type-image-editor__button fieldops-luminaire-frame-type-image-editor__button--tool"
                        :class="tool === 'line' ? 'is-active' : ''"
                        x-on:click="setTool('line')"
                        :aria-pressed="tool === 'line'"
                    >
                        {{ __('fieldops::resource.catalogs.frame_type_editor.tool_line') }}
                    </button>

                    <button
                        type="button"
                        class="fieldops-luminaire-frame-type-image-editor__button fieldops-luminaire-frame-type-image-editor__button--tool"
                        :class="tool === 'rect' ? 'is-active' : ''"
                        x-on:click="setTool('rect')"
                        :aria-pressed="tool === 'rect'"
                    >
                        {{ __('fieldops::resource.catalogs.frame_type_editor.tool_rect') }}
                    </button>

                    <button
                        type="button"
                        class="fieldops-luminaire-frame-type-image-editor__button fieldops-luminaire-frame-type-image-editor__button--tool"
                        :class="tool === 'circle' ? 'is-active' : ''"
                        x-on:click="setTool('circle')"
                        :aria-pressed="tool === 'circle'"
                    >
                        {{ __('fieldops::resource.catalogs.frame_type_editor.tool_circle') }}
                    </button>
                </div>

                <button
                    type="button"
                    class="fieldops-luminaire-frame-type-image-editor__button"
                    x-on:click="loadCurrentIntoCanvas()"
                    :disabled="isUploading || !currentImage"
                >
                    {{ __('fieldops::resource.catalogs.frame_type_editor.load_current') }}
                </button>

                <button
                    type="button"
                    class="fieldops-luminaire-frame-type-image-editor__button"
                    x-on:click="paintBlankCanvas()"
                    :disabled="isUploading"
                >
                    {{ __('fieldops::resource.catalogs.frame_type_editor.clear_canvas') }}
                </button>

                <button
                    type="button"
                    class="fieldops-luminaire-frame-type-image-editor__button fieldops-luminaire-frame-type-image-editor__button--primary"
                    x-on:click="saveDrawing()"
                    :disabled="isUploading"
                >
                    {{ __('fieldops::resource.catalogs.frame_type_editor.save_drawing') }}
                </button>
            </div>

            <div class="fieldops-luminaire-frame-type-image-editor__canvas-shell">
                <canvas
                    x-ref="canvas"
                    class="fieldops-luminaire-frame-type-image-editor__canvas"
                    width="960"
                    height="540"
                    aria-label="{{ __('fieldops::resource.catalogs.frame_type_editor.draw_title') }}"
                    x-on:pointerdown.prevent="startDrawing($event)"
                    x-on:pointermove.prevent="draw($event)"
                    x-on:pointerup.prevent="stopDrawing($event)"
                    x-on:pointerleave.prevent="stopDrawing($event)"
                    x-on:pointercancel.prevent="stopDrawing($event)"
                ></canvas>

                <div class="fieldops-luminaire-frame-type-image-editor__status">
                    {{ __('fieldops::resource.catalogs.frame_type_editor.canvas_hint') }}
                </div>
                <div class="fieldops-luminaire-frame-type-image-editor__status" x-text="`${labels.tool_label}: ${toolLabel(tool)}`"></div>
                <div class="fieldops-luminaire-frame-type-image-editor__status">
                    {{ __('fieldops::resource.catalogs.frame_type_editor.tool_hint') }}
                </div>
            </div>
        </div>
    </div>
</div>
