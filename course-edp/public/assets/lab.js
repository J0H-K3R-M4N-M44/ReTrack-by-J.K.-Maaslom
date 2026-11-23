/* EDP: user events, event delegation, async file I/O, folder-change events, REST/JSON. */
(() => {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const editor = $('note-editor');
  if (!editor) return;
  const setNoteFeedback = (text) => { $('note-feedback').textContent = text; };
  let selectedFileHandle = null;
  let directoryHandle = null;
  let scanTimer = null;
  let scanning = false;
  let knownFiles = null;

  const safeName = () => {
    const value = $('note-file-name').value.trim();
    const name = value.replace(/[^a-zA-Z0-9._-]/g, '_').slice(0, 80);
    return name.toLowerCase().endsWith('.txt') ? name : `${name || 'repair-notes'}.txt`;
  };
  async function openTextFile() {
    if (window.showOpenFilePicker) {
      try {
        const [handle] = await window.showOpenFilePicker({multiple: false, types: [{description: 'Text files', accept: {'text/plain': ['.txt']}}]});
        const file = await handle.getFile();
        if (file.size > 100000) throw new Error('Text file exceeds the 100 KB classroom limit.');
        editor.value = await file.text();
        selectedFileHandle = handle;
        $('note-file-name').value = file.name;
        document.dispatchEvent(new CustomEvent('edp:notepad-opened', {detail: {name: file.name}}));
      } catch (error) { if (error.name !== 'AbortError') setNoteFeedback(error.message || 'Unable to open text file.'); }
    } else { $('note-file-input').click(); }
  }
  async function saveTextFile() {
    const fileName = safeName();
    const text = editor.value;
    if (new Blob([text]).size > 100000) { setNoteFeedback('Save blocked: text exceeds 100 KB.'); return; }
    try {
      if (window.showSaveFilePicker) {
        const handle = selectedFileHandle || await window.showSaveFilePicker({suggestedName: fileName,
          types: [{description: 'Text files', accept: {'text/plain': ['.txt']}}]});
        const writable = await handle.createWritable();
        await writable.write(text);
        await writable.close();
        selectedFileHandle = handle;
      } else {
        const href = URL.createObjectURL(new Blob([text], {type: 'text/plain;charset=utf-8'}));
        const anchor = document.createElement('a');
        anchor.href = href; anchor.download = fileName; document.body.append(anchor); anchor.click(); anchor.remove();
        setTimeout(() => URL.revokeObjectURL(href), 10000);
      }
      document.dispatchEvent(new CustomEvent('edp:notepad-saved', {detail: {name: fileName}}));
    } catch (error) { if (error.name !== 'AbortError') setNoteFeedback(error.message || 'Unable to save file.'); }
  }
  $('note-open').addEventListener('click', openTextFile);
  $('note-save').addEventListener('click', saveTextFile);
  $('note-clear').addEventListener('click', () => { editor.value = ''; selectedFileHandle = null; setNoteFeedback('Editor cleared; no file was changed.'); editor.focus(); });
  $('note-file-input').addEventListener('change', async (event) => {
    const file = event.target.files?.[0];
    if (!file) return;
    if (!file.name.toLowerCase().endsWith('.txt') || file.size > 100000) { setNoteFeedback('Choose a .txt file up to 100 KB.'); return; }
    editor.value = await file.text(); selectedFileHandle = null; $('note-file-name').value = file.name;
    document.dispatchEvent(new CustomEvent('edp:notepad-opened', {detail: {name:file.name}}));
    event.target.value = '';
  });
  document.addEventListener('edp:notepad-opened', (event) => setNoteFeedback(`Opened ${event.detail.name} into the editor.`));
  document.addEventListener('edp:notepad-saved', (event) => setNoteFeedback(`Saved ${event.detail.name}.`));

  async function pollFolder() {
    if (!directoryHandle || scanning) return;
    scanning = true;
    try {
      const seen = new Set();
      for await (const entry of directoryHandle.values()) {
        if (entry.kind === 'file' && entry.name.toLowerCase().endsWith('.txt')) seen.add(entry.name);
      }
      if (knownFiles !== null) {
        for (const name of seen) if (!knownFiles.has(name)) addWatchEvent(`New text file: ${name}`);
        for (const name of knownFiles) if (!seen.has(name)) addWatchEvent(`Text file removed: ${name}`);
      }
      knownFiles = seen;
      $('watch-feedback').textContent = `Monitoring ${directoryHandle.name} · ${seen.size} text file(s).`;
    } catch (error) { stopWatch(`Folder monitoring stopped: ${error.message || 'permission lost'}.`); }
    finally { scanning = false; }
  }
  function addWatchEvent(text) {
    const row = document.createElement('li');
    row.textContent = text;
    $('watch-events').prepend(row);
    while ($('watch-events').children.length > 15) $('watch-events').lastElementChild.remove();
    document.dispatchEvent(new CustomEvent('edp:folder-changed', {detail: {description:text}}));
  }
  function stopWatch(message='Not monitoring.') {
    if (scanTimer) clearInterval(scanTimer);
    scanTimer=null; directoryHandle=null; knownFiles=null;
    $('watch-start').disabled=false; $('watch-stop').disabled=true;
    $('watch-feedback').textContent=message;
  }
  $('watch-start').addEventListener('click', async () => {
    if (!window.showDirectoryPicker) {
      $('watch-feedback').textContent='Folder monitoring needs a browser supporting the File System Access API (Chrome/Edge, localhost/HTTPS).';
      return;
    }
    try {
      const handle=await window.showDirectoryPicker({mode:'read'});
      directoryHandle=handle; knownFiles=null;
      $('watch-start').disabled=true; $('watch-stop').disabled=false;
      await pollFolder();
      if (directoryHandle) scanTimer=setInterval(pollFolder,2000);
    } catch(error) { if(error.name !== 'AbortError') stopWatch('Folder permission was not granted.'); }
  });
  $('watch-stop').addEventListener('click', () => stopWatch());
  window.addEventListener('pagehide', () => stopWatch());

  let activeController=null;
  $('holidays-load').addEventListener('click', async () => {
    const year=$('holiday-year').value;
    if (activeController) activeController.abort();
    const controller=new AbortController(); activeController=controller;
    const button=$('holidays-load'); button.disabled=true;
    $('holiday-feedback').textContent='Loading a public holiday reference…';
    $('holiday-results').replaceChildren();
    const timeout=setTimeout(() => controller.abort(),7500);
    try {
      const response=await fetch(`holiday-api.php?year=${encodeURIComponent(year)}`,
        {signal:controller.signal,credentials:'same-origin',headers:{Accept:'application/json'}});
      const payload=await response.json();
      if (!response.ok || payload.ok !== true || !Array.isArray(payload.data?.holidays)) {
        throw new Error(typeof payload.error==='string'?payload.error:'Holiday data unavailable.');
      }
      const todayParts=Object.fromEntries(new Intl.DateTimeFormat('en-US',{timeZone:'Asia/Manila',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date()).map(part=>[part.type,part.value]));
      const today=`${todayParts.year}-${todayParts.month}-${todayParts.day}`;
      const upcoming=payload.data.holidays.filter(h=>h.date>=today).slice(0,12);
      const list=$('holiday-results');
      for (const holiday of upcoming) {
        const li=document.createElement('li'), time=document.createElement('time');
        time.dateTime=holiday.date; time.textContent=holiday.date;
        li.append(time,document.createTextNode(holiday.name));list.append(li);
      }
      $('holiday-feedback').textContent=upcoming.length
        ? `Showing ${upcoming.length} upcoming national public holidays from Nager.Date.`
        : 'No remaining national public holidays in the selected year.';
      document.dispatchEvent(new CustomEvent('edp:api-complete',{detail:{count:upcoming.length}}));
    } catch(error) {
      $('holiday-feedback').textContent=error.name==='AbortError'
        ? 'Holiday request timed out; try again later.' : `Holiday reference unavailable: ${error.message}`;
    } finally { clearTimeout(timeout);if(activeController===controller){activeController=null;button.disabled=false;} }
  });
})();
