using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Data;
using System.Drawing;
using System.Linq;
using System.Text;
using System.Windows.Forms;
using System.Threading;
using System.Net;
using System.IO;

namespace Updater
{
    public partial class ProgressForm : Form
    {
        private struct FileInfo
        {
            public string Name;
            public int Size;
        }

        private List<FileInfo> mFiles = new List<FileInfo>();
        private bool mCanceled = false;

        public ProgressForm()
        {
            InitializeComponent();
        }

        public void Init(string[] serverResponse)
        {
            try
            {
                mFiles.Clear();
                for (int i = 1; i < serverResponse.Length; ++i)
                {
                    string[] values = serverResponse[i].Split('\t');
                    if (values.Length != 2)
                    {
                        throw new UpdaterException(Properties.Resources.ErrServerVersion);
                    }

                    FileInfo file = new FileInfo();
                    file.Name = values[0];
                    file.Size = int.Parse(values[1]);
                    mFiles.Add(file);
                }
            }
            catch (UpdaterException)
            {
                throw;
            }
            catch (Exception)
            {
                throw new UpdaterException(Properties.Resources.ErrBadServerResponse);
            }
        }

        public bool Canceled
        {
            get { return mCanceled; }
        }

        private void cancelButton_Click(object sender, EventArgs e)
        {
            lock (this)
            {
                mCanceled = true;
            }
        }

        private void ProgressForm_Load(object sender, EventArgs e)
        {
            Thread thread = new Thread(Run);
            thread.Start();
        }

        private delegate void SetProgressDelegate(int progress);
        private void SetProgress(int progress)
        {
            if (InvokeRequired)
            {
                lock (this)
                {
                    if (mCanceled)
                    {
                        throw new CancelException();
                    }
                }
                Invoke(new SetProgressDelegate(SetProgress), new object[] { progress });
            }
            else
            {
                progressBar1.Value = progress;
            }
        }

        private delegate void CompleteDelegate();
        private void Complete()
        {
            if (InvokeRequired)
            {
                Invoke(new CompleteDelegate(Complete));
            }
            else
            {
                Close();
            }
        }

        private delegate void ShowErrorDelegate(Exception ex);
        private void ShowError(Exception ex)
        {
            if (InvokeRequired)
            {
                Invoke(new ShowErrorDelegate(ShowError), new object[] { ex });
            }
            else
            {
                MessageBox.Show(ex.Message, Properties.Resources.Error, MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
        }

        private class CancelException : Exception
        {
            public CancelException() { }
        }

        private int Percent(long progress, long total)
        {
            int percent = (int)Math.Round((((double)progress) * 100) / ((double)total));
            return Math.Min(Math.Max(percent, 0), 100);
        }

        private byte[] mBuffer = new byte[1024];
        private long DownloadFile(FileInfo file, long progress, long total)
        {
            int percent = Percent(progress, total);
            Uri uri = new Uri(Program.BaseUrl + file.Name);
            HttpWebRequest request = (HttpWebRequest)WebRequest.Create(uri);
            request.Method = "GET";

            string fileName = Program.LocalTmpPath + file.Name.Replace('/', Path.DirectorySeparatorChar);
            string dir = fileName.Substring(0, fileName.LastIndexOf(Path.DirectorySeparatorChar));
            if (dir.Length > 0)
            {
                Directory.CreateDirectory(dir);
            }

            string updates = string.Empty;
            using (HttpWebResponse response = (HttpWebResponse)request.GetResponse())
            {
                using (Stream responseStream = response.GetResponseStream())
                {
                    using (FileStream outFile = new FileStream(fileName, FileMode.Create))
                    {
                        int bytesRead;
                        while ((bytesRead = responseStream.Read(mBuffer, 0, mBuffer.Length)) != 0)
                        {
                            outFile.Write(mBuffer, 0, bytesRead);
                            progress += bytesRead;
                            int newPercent = Percent(progress, total);
                            if (newPercent > percent)
                            {
                                percent = newPercent;
                                SetProgress(percent);
                            }
                        }
                    }
                }
            }
            return progress;
        }

        private bool MoveDownloadedFile(FileInfo file)
        {
            if (file.Name.IndexOf("Updater") >= 0)
            {
                return false;
            }
            string fileName = file.Name.Replace('/', Path.DirectorySeparatorChar);
            string src = Program.LocalTmpPath + fileName;
            string dst = Program.LocalPath + fileName;
            File.Delete(dst);
            File.Move(src, dst);
            return true;
        }

        private void Run()
        {
            try
            {
                long total = 0;
                foreach (FileInfo file in mFiles)
                {
                    total += file.Size;
                }

                long progress = 0;
                foreach (FileInfo file in mFiles)
                {
                    progress = DownloadFile(file, progress, total);
                }

                bool allMoved = true;
                foreach (FileInfo file in mFiles)
                {
                    if (!MoveDownloadedFile(file))
                    {
                        allMoved = false;
                    }
                }

                if (allMoved)
                {
                    Directory.Delete(Program.LocalTmpPath, true);
                }
            }
            catch (CancelException)
            {
            }
            catch (Exception ex)
            {
                ShowError(ex);
                lock (this)
                {
                    mCanceled = true;
                }
            }
            finally
            {
                Thread.Sleep(500);
                Complete();
            }
        }
    }
}
