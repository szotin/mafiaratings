using System;
using System.Collections.Generic;
using System.Linq;
using System.Windows.Forms;
using System.IO;

namespace MafiaRatings
{
    static class Program
    {
        public static string Lang;

/*        private static void Move(string srcDir, string dstDir)
        {
            Directory.CreateDirectory(dstDir);

            string[] files = Directory.GetFiles(srcDir);
            foreach (string srcFile in files)
            {
                string pureFileName;
                int i = srcFile.LastIndexOf(Path.DirectorySeparatorChar);
                if (i < 0)
                {
                    pureFileName = srcFile;
                }
                else
                {
                    pureFileName = srcFile.Substring(i + 1);
                }

                string dstFile = dstDir + pureFileName;

                File.Delete(dstFile);
                File.Move(srcFile, dstFile);
            }

            string[] dirs = Directory.GetDirectories(srcDir);
            foreach (string src in dirs)
            {
                string pureDirName;
                int i = src.LastIndexOf(Path.DirectorySeparatorChar);
                if (i < 0)
                {
                    pureDirName = src;
                }
                else
                {
                    pureDirName = src.Substring(i + 1);
                }
                string dst = dstDir + pureDirName;

                Move(src + Path.DirectorySeparatorChar, dst + Path.DirectorySeparatorChar);
            }

            Directory.Delete(srcDir);
        }*/

        /// <summary>
        /// The main entry point for the application.
        /// </summary>
        [STAThread]
        static void Main(string[] args)
        {
            if (args.Length > 0)
            {
                Lang = args[0];
            }

            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);
            Application.Run(new MainForm());

            // check if launcher was updated
/*            string dstDir = Path.GetDirectoryName(Application.ExecutablePath);
            if (!dstDir.EndsWith(Path.DirectorySeparatorChar.ToString()))
            {
                dstDir += Path.DirectorySeparatorChar;
            }
            string srcDir = dstDir + "_updater" + Path.DirectorySeparatorChar;
            try
            {
                Move(srcDir, dstDir);
            }
            catch (Exception)
            {
            }*/
        }
    }
}
