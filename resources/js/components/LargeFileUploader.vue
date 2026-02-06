<template>
  <div>
    <input type="file" @change="onPick" />

    <div v-if="file" style="margin-top:12px;">
      <div>{{ file.name }} ({{ formatBytes(file.size) }})</div>

      <progress :value="progress" max="100" style="width:360px;"></progress>
      <span style="margin-left:8px;">{{ progress.toFixed(1) }}%</span>

      <div style="margin-top:10px;">
        <button @click="startOrResume" :disabled="uploading">開始/續傳</button>
        <button @click="cancel" :disabled="!uploading">取消</button>
      </div>

      <div style="margin-top:10px;">{{ statusText }}</div>

      <div v-if="downloadUrl" style="margin-top:10px;">
        <a :href="downloadUrl" target="_blank">受控下載（推薦）</a>
      </div>

      <div v-if="publicUrl" style="margin-top:6px;">
        <a :href="publicUrl" target="_blank">公開 URL</a>
      </div>
    </div>
  </div>
</template>

<script>
import axios from "axios";
import SparkMD5 from "spark-md5";

export default {
  name: "LargeFileUploader",
  data() {
    return {
      file: null,

      chunkSize: 10 * 1024 * 1024, // 10MB（後端限制要 >= 10MB）
      concurrency: 3,            // 並行數 2~6 自己調

      uploading: false,
      statusText: "",
      progress: 0,

      uploadId: "",
      fileHash: "",
      totalChunks: 0,

      uploadedBytesByChunk: {},

      abortController: null,

      publicUrl: "",
      downloadUrl: "",
      md5Progress: 0,
      completedChunks: 0,   // 已完成 chunk 數
      totalToUpload: 0,     // 本次要上傳的 chunk 總數
    };
  },
  methods: {
    onPick(e) {
      const f = e.target.files?.[0];
      if (!f) return;

      this.file = f;
      this.totalChunks = Math.ceil(f.size / this.chunkSize);

      this.uploading = false;
      this.statusText = "";
      this.progress = 0;

      this.uploadId = "";
      this.fileHash = "";
      this.uploadedBytesByChunk = {};

      this.publicUrl = "";
      this.downloadUrl = "";
    },

    async startOrResume() {
      if (!this.file) return;

      this.uploading = true;
      this.statusText = "計算檔案識別碼（MD5）...";
      this.abortController = new AbortController();

      try {
        // 1) 先算 MD5（穩定續傳）
        if (!this.fileHash) {
          this.fileHash = await this.calcFileMD5(this.file);
        }

        // 2) init 拿 uploadId（此實作會用 hash 當 uploadId）
        this.statusText = "初始化上傳...";
        const initRes = await axios.post(
          "/api/large-file/init",
          {
            fileName: this.file.name,
            fileSize: this.file.size,
            totalChunks: this.totalChunks,
            fileHash: this.fileHash,
          },
          { signal: this.abortController.signal }
        );
        this.uploadId = initRes.data.uploadId;

        // 3) status 查已存在 chunks（續傳）
        this.statusText = "檢查已上傳分段（續傳）...";
        const statusRes = await axios.get("/api/large-file/status", {
          params: { uploadId: this.uploadId, totalChunks: this.totalChunks },
          signal: this.abortController.signal,
        });

        const uploaded = new Set(statusRes.data.uploadedChunks || []);
        // 已完成 chunk 直接計入進度
        for (const n of uploaded) {
          this.uploadedBytesByChunk[n] = this.getChunkSizeBytes(n);
        }
        this.updateProgress();

        // 4) 建 queue（只上傳缺的 chunk）
        const queue = [];
        for (let n = 1; n <= this.totalChunks; n++) {
          if (!uploaded.has(n)) queue.push(n);
        }

        if (queue.length > 0) {
          this.totalToUpload = queue.length;
          this.completedChunks = 0;

          this.statusText = `上傳中（已上傳 ${this.completedChunks} / ${this.totalToUpload}）...`;
          await this.uploadQueueConcurrently(queue);
        }

        // 5) merge
        this.statusText = "合併檔案中...";
        const mergeRes = await axios.post(
          "/api/large-file/merge",
          {
            uploadId: this.uploadId,
            totalChunks: this.totalChunks,
            fileName: this.file.name,
            fileSize: this.file.size, // 給後端校驗用（建議）
          },
          { signal: this.abortController.signal }
        );

        this.publicUrl = mergeRes.data.publicUrl || "";
        this.downloadUrl = mergeRes.data.downloadUrl || "";

        this.progress = 100;
        this.statusText = "完成 ✅";
      } catch (err) {
        if (err?.name === "CanceledError" || err?.code === "ERR_CANCELED") {
          this.statusText = "已取消（可再按開始/續傳）";
        } else {
          this.statusText = `失敗：${err?.response?.data?.message || err.message}`;
        }
      } finally {
        this.uploading = false;
      }
    },

    cancel() {
      if (this.abortController) this.abortController.abort();
      this.uploading = false;
    },

    async uploadQueueConcurrently(queue) {
      const worker = async () => {
        while (queue.length > 0 && this.uploading) {
          const chunkNo = queue.shift();
          await this.uploadOneChunk(chunkNo);
        }
      };

      const workers = [];
      for (let i = 0; i < this.concurrency; i++) workers.push(worker());
      await Promise.all(workers);
    },

    async uploadOneChunk(chunkNo) {
      const { blob, size } = this.getChunk(chunkNo);

      const form = new FormData();
      form.append("uploadId", this.uploadId);
      form.append("chunkNumber", chunkNo);
      form.append("totalChunks", this.totalChunks);
      form.append("fileName", this.file.name);
      form.append("chunk", blob, `${this.file.name}.part${chunkNo}`);

      // 初始化該 chunk 進度
      this.uploadedBytesByChunk[chunkNo] = 0;
      this.updateProgress();

      // await axios.post("/api/large-file/chunk", form, {
      //   signal: this.abortController.signal,
      //   headers: { "Content-Type": "multipart/form-data" },
      //   onUploadProgress: (evt) => {
      //     this.uploadedBytesByChunk[chunkNo] = Math.min(evt.loaded, size);
      //     this.updateProgress();
      //   },
      // });

      const res = await axios.post('/api/large-file/chunk', form, {
        signal: this.abortController.signal,
        // headers: { "Content-Type": "multipart/form-data" },
        onUploadProgress: (evt) => {
          this.uploadedBytesByChunk[chunkNo] = Math.min(evt.loaded, size);
          this.updateProgress();
        },
      });

      // 記為完成
      this.uploadedBytesByChunk[chunkNo] = size;
      this.updateProgress();

      // ✅ 更新「已上傳 x / 總數」
      if (!res.data?.skipped) {                 // 保險：若後端回 skipped 就不重複計數
        this.completedChunks += 1;
      }
      if (this.completedChunks % 5 === 0 || this.completedChunks === this.totalToUpload) {
        this.statusText = `上傳中（已上傳 ${this.completedChunks} / ${this.totalToUpload}）...`;
      }
    },

    updateProgress() {
      const total = this.file.size;
      let sum = 0;
      for (const k of Object.keys(this.uploadedBytesByChunk)) {
        sum += this.uploadedBytesByChunk[k] || 0;
      }
      this.progress = Math.max(0, Math.min(100, (sum / total) * 100));
    },

    getChunk(chunkNo) {
      const start = (chunkNo - 1) * this.chunkSize;
      const end = Math.min(this.file.size, start + this.chunkSize);
      return { blob: this.file.slice(start, end), size: end - start };
    },

    getChunkSizeBytes(chunkNo) {
      const start = (chunkNo - 1) * this.chunkSize;
      const end = Math.min(this.file.size, start + this.chunkSize);
      return end - start;
    },

    // 分段讀取算 MD5（避免一次讀爆）
    calcFileMD5(file) {
      const hashChunkSize = 4 * 1024 * 1024;
      const chunks = Math.ceil(file.size / hashChunkSize);
      const spark = new SparkMD5.ArrayBuffer();
      let current = 0;

      console.log(`[MD5] start, total chunks = ${chunks}`);

      return new Promise((resolve, reject) => {
        const reader = new FileReader();

        reader.onload = (e) => {
          spark.append(e.target.result);
          current++;

          const percent = ((current / chunks) * 100).toFixed(2);
          this.md5Progress = percent;
          this.statusText = `計算檔案識別碼（MD5） ${percent}%`;
          // ✅ console.log 進度
          // console.log(`[MD5] ${current}/${chunks} (${percent}%)`);

          if (current < chunks) {
            loadNext();
          } else {
            const hash = spark.end();
            console.log(`[MD5] done, hash = ${hash}`);
            resolve(hash);
          }
        };

        reader.onerror = () => {
          console.error("[MD5] FileReader error");
          reject(new Error("MD5 計算失敗"));
        };

        const loadNext = () => {
          const start = current * hashChunkSize;
          const end = Math.min(file.size, start + hashChunkSize);
          reader.readAsArrayBuffer(file.slice(start, end));
        };

        loadNext();
      });
    },

    formatBytes(bytes) {
      const units = ["B", "KB", "MB", "GB", "TB"];
      let n = bytes;
      let i = 0;
      while (n >= 1024 && i < units.length - 1) {
        n /= 1024;
        i++;
      }
      return `${n.toFixed(1)} ${units[i]}`;
    },
  },
};
</script>