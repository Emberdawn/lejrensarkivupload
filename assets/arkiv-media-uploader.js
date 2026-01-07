(function () {
  const root = document.querySelector('[data-arkiv-media-uploader]');
  if (!root) {
    return;
  }

  const dropzone = root.querySelector('[data-dropzone]');
  const fileInput = root.querySelector('[data-file-input]');
  const fileList = root.querySelector('[data-file-list]');
  const selectButton = root.querySelector('[data-select-files]');
  const authUser = root.querySelector('[data-auth-user]');
  const authPass = root.querySelector('[data-auth-pass]');
  const maxConcurrent = 4;
  const state = {
    active: 0,
    items: [],
  };

  const restRoot =
    window.wpApiSettings && window.wpApiSettings.root
      ? window.wpApiSettings.root
      : '/wp-json/';

  function buildEndpoint() {
    try {
      return new URL('wp/v2/media', restRoot).toString();
    } catch (error) {
      return `${restRoot.replace(/\\/+$/, '')}/wp/v2/media`;
    }
  }

  function formatBytes(bytes) {
    if (!bytes && bytes !== 0) return '';
    const units = ['B', 'KB', 'MB', 'GB'];
    let value = bytes;
    let unitIndex = 0;
    while (value >= 1024 && unitIndex < units.length - 1) {
      value /= 1024;
      unitIndex += 1;
    }
    return `${value.toFixed(value >= 10 || unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
  }

  function getAuthHeader() {
    const username = authUser ? authUser.value.trim() : '';
    const password = authPass ? authPass.value.trim() : '';
    if (!username || !password) {
      return null;
    }
    return `Basic ${btoa(`${username}:${password}`)}`;
  }

  function getNonceHeader() {
    if (window.wpApiSettings && window.wpApiSettings.nonce) {
      return window.wpApiSettings.nonce;
    }
    return null;
  }

  const statusLabels = {
    queued: 'Queued',
    uploading: 'Uploading',
    done: 'Done',
    error: 'Error',
  };

  function createRow(file) {
    const row = document.createElement('div');
    row.className = 'arkiv-media-row';

    const thumb = document.createElement('img');
    thumb.className = 'arkiv-media-thumb';
    thumb.alt = file.name;
    thumb.loading = 'lazy';
    row.appendChild(thumb);

    const meta = document.createElement('div');
    meta.className = 'arkiv-media-meta';
    const name = document.createElement('div');
    name.className = 'arkiv-media-name';
    name.textContent = file.name;
    const size = document.createElement('div');
    size.className = 'arkiv-media-size';
    size.textContent = formatBytes(file.size);
    meta.appendChild(name);
    meta.appendChild(size);
    row.appendChild(meta);

    const status = document.createElement('div');
    status.className = 'arkiv-media-status';
    status.textContent = statusLabels.queued;
    row.appendChild(status);

    const progress = document.createElement('div');
    progress.className = 'arkiv-media-progress';
    const progressBar = document.createElement('div');
    progressBar.className = 'arkiv-media-progress-bar';
    const progressFill = document.createElement('span');
    progressBar.appendChild(progressFill);
    const progressText = document.createElement('div');
    progressText.className = 'arkiv-media-progress-text';
    progressText.textContent = '0%';
    progress.appendChild(progressBar);
    progress.appendChild(progressText);
    row.appendChild(progress);

    const result = document.createElement('div');
    result.className = 'arkiv-media-result';
    row.appendChild(result);

    const actions = document.createElement('div');
    actions.className = 'arkiv-media-actions';
    const retryButton = document.createElement('button');
    retryButton.type = 'button';
    retryButton.textContent = 'Retry';
    retryButton.classList.add('is-hidden');
    actions.appendChild(retryButton);
    row.appendChild(actions);

    if (file.type.startsWith('image/')) {
      const url = URL.createObjectURL(file);
      thumb.src = url;
      thumb.addEventListener(
        'load',
        () => {
          URL.revokeObjectURL(url);
        },
        { once: true }
      );
    }

    return {
      file,
      row,
      thumb,
      status,
      progressFill,
      progressText,
      result,
      retryButton,
      state: 'queued',
      xhr: null,
      media: null,
    };
  }

  function setStatus(item, statusKey, message) {
    item.state = statusKey;
    item.status.textContent = statusLabels[statusKey] || statusKey;
    item.status.classList.remove('is-uploading', 'is-done', 'is-error');
    if (statusKey === 'uploading') {
      item.status.classList.add('is-uploading');
    }
    if (statusKey === 'done') {
      item.status.classList.add('is-done');
    }
    if (statusKey === 'error') {
      item.status.classList.add('is-error');
    }
    if (message) {
      const detail = document.createElement('div');
      detail.className = statusKey === 'error' ? 'arkiv-media-error' : 'arkiv-media-result';
      detail.textContent = message;
      item.result.innerHTML = '';
      item.result.appendChild(detail);
    }
  }

  function setProgress(item, percent) {
    const safePercent = Math.max(0, Math.min(100, percent));
    item.progressFill.style.width = `${safePercent}%`;
    item.progressText.textContent = `${Math.round(safePercent)}%`;
  }

  function handleResponse(item, response) {
    const mediaId = response && response.id ? response.id : null;
    const mediaUrl =
      response && response.source_url
        ? response.source_url
        : response && response.guid && response.guid.rendered
        ? response.guid.rendered
        : null;
    item.media = { id: mediaId, url: mediaUrl };
    const parts = [];
    if (mediaId) {
      parts.push(`ID: ${mediaId}`);
    }
    if (mediaUrl) {
      parts.push(`URL: ${mediaUrl}`);
    }
    item.result.innerHTML = '';
    item.result.textContent = parts.length ? parts.join(' · ') : 'Uploaded';
  }

  function handleError(item, message) {
    setStatus(item, 'error', message || 'Upload failed');
    item.retryButton.classList.remove('is-hidden');
  }

  function startUpload(item) {
    const endpoint = buildEndpoint();
    const xhr = new XMLHttpRequest();
    item.xhr = xhr;
    setStatus(item, 'uploading');
    item.retryButton.classList.add('is-hidden');
    setProgress(item, 0);
    state.active += 1;

    xhr.open('POST', endpoint, true);
    xhr.responseType = 'json';
    xhr.setRequestHeader('Content-Type', item.file.type || 'application/octet-stream');
    const safeName = item.file.name.replace(/"/g, "'");
    xhr.setRequestHeader('Content-Disposition', `attachment; filename="${safeName}"`);

    const authHeader = getAuthHeader();
    const nonce = getNonceHeader();
    if (nonce) {
      xhr.setRequestHeader('X-WP-Nonce', nonce);
    } else if (authHeader) {
      xhr.setRequestHeader('Authorization', authHeader);
    }

    xhr.upload.addEventListener('progress', (event) => {
      if (!event.lengthComputable) {
        return;
      }
      const percent = (event.loaded / event.total) * 100;
      setProgress(item, percent);
      item.progressText.textContent = `Uploading ${Math.round(percent)}%`;
    });

    xhr.addEventListener('load', () => {
      state.active -= 1;
      setProgress(item, 100);
      let response = xhr.response;
      if (!response && xhr.responseText) {
        try {
          response = JSON.parse(xhr.responseText);
        } catch (error) {
          response = null;
        }
      }
      if (xhr.status >= 200 && xhr.status < 300 && response) {
        setStatus(item, 'done');
        handleResponse(item, response);
      } else {
        const message = response && response.message ? response.message : xhr.statusText;
        handleError(item, message || 'Upload failed');
      }
      scheduleUploads();
    });

    xhr.addEventListener('error', () => {
      state.active -= 1;
      handleError(item, 'Network error');
      scheduleUploads();
    });

    xhr.send(item.file);
  }

  function scheduleUploads() {
    while (state.active < maxConcurrent) {
      const next = state.items.find((item) => item.state === 'queued');
      if (!next) {
        return;
      }
      startUpload(next);
    }
  }

  function addFiles(files) {
    Array.from(files).forEach((file) => {
      const item = createRow(file);
      item.retryButton.addEventListener('click', () => {
        item.result.innerHTML = '';
        setStatus(item, 'queued');
        setProgress(item, 0);
        scheduleUploads();
      });
      fileList.appendChild(item.row);
      state.items.push(item);
    });
    scheduleUploads();
  }

  if (selectButton && fileInput) {
    selectButton.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', (event) => {
      if (!event.target.files) return;
      addFiles(event.target.files);
      fileInput.value = '';
    });
  }

  if (dropzone) {
    dropzone.addEventListener('dragover', (event) => {
      event.preventDefault();
      dropzone.classList.add('is-dragover');
    });
    dropzone.addEventListener('dragleave', () => {
      dropzone.classList.remove('is-dragover');
    });
    dropzone.addEventListener('drop', (event) => {
      event.preventDefault();
      dropzone.classList.remove('is-dragover');
      if (event.dataTransfer && event.dataTransfer.files) {
        addFiles(event.dataTransfer.files);
      }
    });
  }
})();
