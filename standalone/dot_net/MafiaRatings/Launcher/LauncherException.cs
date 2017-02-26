using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;

namespace Launcher
{
    class LauncherException : Exception
    {
        public LauncherException(string message)
            : base(message)
        {
        }
    }
}
