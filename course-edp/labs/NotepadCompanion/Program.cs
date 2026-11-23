using System;
using System.Drawing;
using System.IO;
using System.Text;
using System.Windows.Forms;

namespace RepairTrackNotepad;

internal static class Program
{
    [STAThread]
    private static void Main()
    {
        ApplicationConfiguration.Initialize();
        Application.Run(new NotepadForm());
    }
}

/// <summary>
/// Activity 9: a small standalone Windows companion for the RepairTrack EDP lab.
/// Works on local .txt files. It never connects to customer accounts or MySQL.
/// </summary>
internal sealed class NotepadForm : Form
{
    private readonly TextBox editor = new()
    {
        Multiline = true, Dock = DockStyle.Fill, AcceptsReturn = true,
        AcceptsTab = true, ScrollBars = ScrollBars.Both,
        Font = new Font("Consolas", 11), WordWrap = false
    };
    private readonly ListBox notifications = new() { Dock = DockStyle.Fill };
    private readonly Label status = new() { AutoSize = true, Text = "Ready. No file selected." };
    private FileSystemWatcher? watcher;
    private string? currentPath;

    public NotepadForm()
    {
        Text = "RepairTrack EDP — Notepad and File Monitor";
        StartPosition = FormStartPosition.CenterScreen;
        MinimumSize = new Size(600, 440);
        Size = new Size(960, 640);
        var toolbar = new FlowLayoutPanel { Dock = DockStyle.Fill, AutoSize = true, Padding = new Padding(12) };
        AddButton(toolbar, "Open .txt", (_, _) => OpenFile());
        AddButton(toolbar, "Save .txt", (_, _) => SaveFile());
        AddButton(toolbar, "Save as…", (_, _) => SaveFileAs());
        AddButton(toolbar, "Monitor folder", (_, _) => ChooseFolder());
        AddButton(toolbar, "Stop monitor", (_, _) => StopMonitor());

        var monitoring = new GroupBox { Dock = DockStyle.Fill, Text = "FileSystemWatcher — .txt changes", Padding = new Padding(8) };
        monitoring.Controls.Add(notifications);
        var layout = new TableLayoutPanel { Dock = DockStyle.Fill, ColumnCount = 1, RowCount = 4 };
        layout.RowStyles.Add(new RowStyle(SizeType.AutoSize));
        layout.RowStyles.Add(new RowStyle(SizeType.Percent, 72));
        layout.RowStyles.Add(new RowStyle(SizeType.Percent, 28));
        layout.RowStyles.Add(new RowStyle(SizeType.AutoSize));
        layout.Controls.Add(toolbar, 0, 0);
        layout.Controls.Add(editor, 0, 1);
        layout.Controls.Add(monitoring, 0, 2);
        layout.Controls.Add(status, 0, 3);
        Controls.Add(layout);
    }

    private static void AddButton(Control parent, string caption, EventHandler handler)
    {
        var button = new Button { Text = caption, AutoSize = true, MinimumSize = new Size(110, 40) };
        button.Click += handler;
        parent.Controls.Add(button);
    }

    private void OpenFile()
    {
        using var dialog = new OpenFileDialog
        {
            Filter = "Text files (*.txt)|*.txt", CheckFileExists = true, Multiselect = false,
            Title = "Open a local text file"
        };
        if (dialog.ShowDialog(this) != DialogResult.OK) return;
        try
        {
            var bytes = new FileInfo(dialog.FileName).Length;
            if (bytes > 100_000) throw new IOException("Use a text file smaller than 100 KB for the lab.");
            editor.Text = File.ReadAllText(dialog.FileName, new UTF8Encoding(false, true));
            currentPath = dialog.FileName;
            status.Text = "Opened: " + Path.GetFileName(currentPath);
        }
        catch (Exception error) when (error is IOException or UnauthorizedAccessException or DecoderFallbackException)
        {
            MessageBox.Show(this, error.Message, "Cannot open file", MessageBoxButtons.OK, MessageBoxIcon.Warning);
        }
    }

    private void SaveFile()
    {
        if (currentPath is null) { SaveFileAs(); return; }
        WriteText(currentPath);
    }

    private void SaveFileAs()
    {
        using var dialog = new SaveFileDialog
        {
            Filter = "Text files (*.txt)|*.txt", AddExtension = true, DefaultExt = "txt",
            FileName = currentPath is null ? "repair-notes.txt" : Path.GetFileName(currentPath),
            Title = "Save the local text file"
        };
        if (dialog.ShowDialog(this) != DialogResult.OK) return;
        WriteText(dialog.FileName);
    }

    private void WriteText(string path)
    {
        try
        {
            var bytes = Encoding.UTF8.GetBytes(editor.Text);
            if (bytes.Length > 100_000) throw new IOException("Text is larger than the 100 KB lab limit.");
            File.WriteAllText(path, editor.Text, new UTF8Encoding(false));
            currentPath = path;
            status.Text = "Saved: " + Path.GetFileName(path);
        }
        catch (Exception error) when (error is IOException or UnauthorizedAccessException)
        {
            MessageBox.Show(this, error.Message, "Cannot save file", MessageBoxButtons.OK, MessageBoxIcon.Warning);
        }
    }

    private void ChooseFolder()
    {
        using var dialog = new FolderBrowserDialog { Description = "Monitor a folder containing .txt files" };
        if (dialog.ShowDialog(this) != DialogResult.OK) return;
        StopMonitor();
        try
        {
            watcher = new FileSystemWatcher(dialog.SelectedPath, "*.txt")
            {
                IncludeSubdirectories = false,
                NotifyFilter = NotifyFilters.FileName | NotifyFilters.LastWrite | NotifyFilters.CreationTime,
            };
            watcher.Created += OnFileChanged;
            watcher.Changed += OnFileChanged;
            watcher.Deleted += OnFileChanged;
            watcher.Renamed += OnFileRenamed;
            watcher.Error += OnWatcherError;
            watcher.EnableRaisingEvents = true;
            status.Text = "Monitoring local folder: " + dialog.SelectedPath;
            Notify("Watching text files: " + dialog.SelectedPath);
        }
        catch (Exception error) when (error is IOException or UnauthorizedAccessException or ArgumentException)
        {
            StopMonitor();
            MessageBox.Show(this, error.Message, "Cannot monitor folder", MessageBoxButtons.OK, MessageBoxIcon.Warning);
        }
    }

    private void OnFileChanged(object sender, FileSystemEventArgs eventArgs) =>
        Notify($"{eventArgs.ChangeType}: {eventArgs.Name}");

    private void OnFileRenamed(object sender, RenamedEventArgs eventArgs) =>
        Notify($"Renamed: {eventArgs.OldName} → {eventArgs.Name}");

    private void OnWatcherError(object sender, ErrorEventArgs eventArgs) =>
        Notify("Monitoring error: " + eventArgs.GetException().Message);

    private void Notify(string message)
    {
        if (IsDisposed || Disposing || !IsHandleCreated) return;
        if (InvokeRequired)
        {
            try { BeginInvoke((Action)(() => Notify(message))); }
            catch (InvalidOperationException) { /* Window is closing. */ }
            return;
        }
        notifications.Items.Insert(0, $"{DateTime.Now:HH:mm:ss}  {message}");
        while (notifications.Items.Count > 100) notifications.Items.RemoveAt(100);
    }

    private void StopMonitor()
    {
        if (watcher is not null)
        {
            watcher.EnableRaisingEvents = false;
            watcher.Dispose();
            watcher = null;
            status.Text = "Folder monitoring stopped.";
        }
    }

    protected override void OnFormClosed(FormClosedEventArgs e)
    {
        StopMonitor();
        base.OnFormClosed(e);
    }
}
