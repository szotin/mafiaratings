using System;
using System.Collections.Generic;
using System.Linq;
using System.Windows.Forms;
using System.Threading;
using System.Diagnostics;
using System.Text;
using Microsoft.Win32;
using System.Net;
using System.IO;

namespace Launcher
{
    static class Program
    {
        
        private const string HOST_REGISTRY_KEY = "host";
#if DEBUG
        public const string DEFAULT_HOST_URL = "http://localhost";
#else
        public const string DEFAULT_HOST_URL = "http://mafiaratings.com";
#endif

        private const string VERSION_REGISTRY_KEY = "version";
        public const int DEFAULT_VERSION = 0;

        private static int mVersion = DEFAULT_VERSION;
        private static string mHostUrl = DEFAULT_HOST_URL;
        private static string mLang;

        private static string GetPath(string appPath)
        {
            string path = Path.GetDirectoryName(appPath);
            if (!path.EndsWith(Path.DirectorySeparatorChar.ToString()))
            {
                path += Path.DirectorySeparatorChar;
            }
            return path;
        }

        public static string BaseUrl
        {
            get { return mHostUrl + "/downloads/windows/"; }
        }

        private static Process CreateProcess(string[] args)
        {
            Process process = new Process();
            if (args.Length > 0)
            {
                StringBuilder arguments = new StringBuilder(args[0]);
                for (int i = 1; i < args.Length; ++i)
                {
                    arguments.Append(' ');
                    arguments.Append(args[i]);
                }
                process.StartInfo.Arguments = arguments.ToString();
            }
            return process;
        }

        private static void StartMafiaRatings(string[] args)
        {
            Process process = CreateProcess(args);
            process.StartInfo.FileName = GetPath(Application.ExecutablePath) + "MafiaRatings.exe";
            try
            {
                process.Start();
            }
            catch (Exception)
            {
                MessageBox.Show(Properties.Resources.ErrNoMafiaRatings, Properties.Resources.Error, MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
        }

        private static void StartUpdater(string[] args)
        {
            Process process = CreateProcess(args);
            process.StartInfo.FileName = GetPath(Application.ExecutablePath) + "Updater.exe";
            process.StartInfo.UseShellExecute = true;
            process.StartInfo.Verb = "runas";
            try
            {
                process.Start();
            }
            catch (Exception ex)
            {
                MessageBox.Show(string.Format(Properties.Resources.ErrNoUpdater, ex.Message), Properties.Resources.Error, MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
        }

        private static void Init()
        {
            RegistryKey key = Registry.LocalMachine.OpenSubKey("Software\\MafiaRatings");
            if (key != null)
            {
                try
                {
                    mHostUrl = key.GetValue(HOST_REGISTRY_KEY, DEFAULT_HOST_URL).ToString();
                    mVersion = (int)key.GetValue(VERSION_REGISTRY_KEY, DEFAULT_VERSION);
                }
                catch (Exception)
                {
                }
            }
        }

        private static string ServerRequest()
        {
            Uri uri = new Uri(BaseUrl + "version.php?v=" + mVersion.ToString());
            HttpWebRequest request = (HttpWebRequest)WebRequest.Create(uri);
            request.Method = "GET";

            string updates = string.Empty;
            using (HttpWebResponse response = (HttpWebResponse)request.GetResponse())
            {
                using (Stream responseStream = response.GetResponseStream())
                {
                    using (StreamReader readStream = new StreamReader(responseStream, Encoding.UTF8))
                    {
                        updates = readStream.ReadToEnd();
                    }
                }
            }
            return updates;
        }

        /// <summary>
        /// The main entry point for the application.
        /// </summary>
        [STAThread]
        static void Main(string[] args)
        {
            if (args.Length > 0)
            {
                mLang = args[0];
                Thread.CurrentThread.CurrentUICulture = new System.Globalization.CultureInfo(mLang);
            }

            bool startUpdater = false;
            try
            {
                Init();

                string response = ServerRequest();
                int i = response.IndexOf('\n');
                string v;
                if (i < 0)
                {
                    v = response;
                }
                else
                {
                    v = response.Substring(0, i);
                }

                int newVersion = int.Parse(v);
                if (newVersion < mVersion)
                {
                    throw new LauncherException(Properties.Resources.ErrServerVersion);
                }
                if (newVersion > mVersion)
                {
                    MessageBox.Show(Properties.Resources.NewVersion, Properties.Resources.Attention, MessageBoxButtons.OK, MessageBoxIcon.Information);
                    File.WriteAllText(GetPath(Application.CommonAppDataPath) + "updater", response);
                    startUpdater = true;
                }
            }
            catch (LauncherException lex)
            {
                MessageBox.Show(lex.Message, Properties.Resources.Error, MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
            catch (Exception ex)
            {
                Console.WriteLine(ex.Message);
                Console.WriteLine(ex.StackTrace);
            }

            if (startUpdater)
            {
                StartUpdater(args);
            }
            else
            {
                StartMafiaRatings(args);
            }
        }
    }
}
