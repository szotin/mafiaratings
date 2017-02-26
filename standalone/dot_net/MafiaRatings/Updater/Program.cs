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

namespace Updater
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

        private const string LOCAL_TMP_PATH = "_updater\\";

        private static int mVersion = DEFAULT_VERSION;
        private static string mHostUrl = DEFAULT_HOST_URL;
        private static string mLang;
        private static string mLocalTmpPath;
        private static string mLocalPath;

        public static string LocalTmpPath
        {
            get { return mLocalTmpPath; }
        }

        public static string LocalPath
        {
            get { return mLocalPath; }
        }

        public static string BaseUrl
        {
            get { return mHostUrl + "/downloads/windows/"; }
        }

        private static void StartMafiaRatings(string[] args)
        {
            Process process = new Process();
            process.StartInfo.FileName = GetPath(Application.ExecutablePath) + "MafiaRatings.exe";
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
            try
            {
                process.Start();
            }
            catch (Exception ex)
            {
                MessageBox.Show(string.Format(Properties.Resources.ErrNoMafiaRatings, ex.Message), Properties.Resources.Error, MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
        }

        private static string GetPath(string appPath)
        {
            string path = Path.GetDirectoryName(appPath);
            if (!path.EndsWith(Path.DirectorySeparatorChar.ToString()))
            {
                path += Path.DirectorySeparatorChar;
            }
            return path;
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

            mLocalPath = GetPath(Application.ExecutablePath);
            mLocalTmpPath = mLocalPath + LOCAL_TMP_PATH;
        }

        private static void SetVersion(int version)
        {
            RegistryKey key = Registry.LocalMachine.OpenSubKey("Software\\MafiaRatings", true);
            if (key != null)
            {
                try
                {
                    key.SetValue(VERSION_REGISTRY_KEY, version);
                    return;
                }
                catch (Exception)
                {
                }
            }
            MessageBox.Show(Properties.Resources.ErrSetVersion, Properties.Resources.Error, MessageBoxButtons.OK, MessageBoxIcon.Error);
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

            try
            {
                Init();

                string updaterFileName = GetPath(Application.CommonAppDataPath) + "updater";
                string[] response = File.ReadAllText(updaterFileName).Split('\n');

                int newVersion = int.Parse(response[0]);
                if (newVersion < mVersion)
                {
                    throw new UpdaterException(Properties.Resources.ErrServerVersion);
                }

                if (newVersion > mVersion)
                {
                    if (response.Length > 1)
                    {
                        Application.EnableVisualStyles();
                        Application.SetCompatibleTextRenderingDefault(false);

                        ProgressForm form = new ProgressForm();
                        form.Init(response);
                        Application.Run(form);
                        if (form.Canceled)
                        {
                            return;
                        }
                        File.Delete(updaterFileName);
                    }
                    SetVersion(newVersion);
                }
            }
/*            catch (UpdaterException lex)
            {
                MessageBox.Show(lex.Message, Properties.Resources.Error, MessageBoxButtons.OK, MessageBoxIcon.Error);
            }*/
            catch (Exception ex)
            {
                MessageBox.Show(ex.Message, Properties.Resources.Error, MessageBoxButtons.OK, MessageBoxIcon.Error);
            }

            StartMafiaRatings(args);
        }
    }
}
