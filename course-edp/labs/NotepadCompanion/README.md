# Activity 9 — Windows Forms Notepad Companion

The Module 3 manual specifically requires **OpenFileDialog**, **SaveFileDialog** and **FileSystemWatcher**. PHP and browser directory polling are not that .NET class, so this Windows-only C# program implements those APIs explicitly. It is a separate EDP lab companion distributed in the RepairTrack academic repository. The technician's **EDP Lab Tools** web page demonstrates similar browser event handling without claiming to be a native .NET watcher.

On Windows with the .NET 8 SDK, run `dotnet run --project course-edp/labs/NotepadCompanion/RepairTrackNotepad.csproj` from the repository root. Use **Open .txt** and **Save .txt** to practice `File.ReadAllText` and `File.WriteAllText`. Use **Monitor folder**, create or rename a `.txt` file in that folder, and check the notification list. Stop monitoring before closing. Use fictional content only; never select the PHP private storage folder or a real customer's files.

A separate Windows GitHub Actions job **compiles this WinForms source**. This does not prove watcher events or the graphical desktop interaction at runtime; capture that evidence manually on Windows.
