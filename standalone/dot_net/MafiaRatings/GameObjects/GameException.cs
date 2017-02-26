using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;

namespace MafiaRatings.GameObjects
{
    class GameException : Exception
    {
        private bool mRecoverable; // recoverable means for example missing connection. Situation that can be fixed once the connection is back.

        public GameException(string message, bool recoverable = false) :
            base(message)
        {
            mRecoverable = recoverable;             
        }

        public bool IsRecoverable
        {
            get { return mRecoverable; }
        }
    }
}
