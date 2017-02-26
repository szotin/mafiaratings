using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;

namespace Updater
{
    class UpdaterException : Exception
    {
        public UpdaterException(string message)
            : base(message)
        {
        }
    }
}
